<?php

namespace Modules\Return\Services;

use App\Support\ReturnPolicy;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;
use Modules\Return\Models\SaleReturn;
use Modules\Return\Models\SaleReturnItem;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleItem;

/**
 * Creates and cancels first-class product-return documents against a sale.
 *
 * On create it persists a SaleReturn (+ items), restocks the goods at their
 * original frozen cost, and posts the balanced ledger entries:
 *
 *   Entry 1 (SaleReturn):
 *     Debit   4010 Sales Revenue        revenueBack
 *     Credit  4020 Sales Discount       discountBack   (proportional policy only)
 *     Credit  <refund account>          refund
 *     Credit  1030 Receivable           reduceReceivable
 *     Credit  4040 Return Deduction     deduction
 *   Entry 2 (SaleReturnCOGS):
 *     Debit   1040 Inventory            costBack
 *     Credit  5010 COGS                 costBack
 *
 * The ledger entries deliberately keep reference_id = sale.id so the customer
 * resolution in ReportService::partyControlLines keeps working for returns.
 * The original sale is never edited.
 */
class ReturnService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const RECEIVABLE_CODE = '1030';

    private const INVENTORY_CODE = '1040';

    private const REVENUE_CODE = '4010';

    private const DISCOUNT_CODE = '4020';

    private const DEDUCTION_CODE = '4040';

    private const COGS_CODE = '5010';

    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /**
     * @param  array<int, array{sale_item_id:int, qty:float}>  $returnItems
     * @param  array{date?:string, refund_amount?:float, refund_account_id?:int, reason?:string, notes?:string, deduction_type?:string, deduction_value?:float}  $options
     */
    public function create(Sale $sale, array $returnItems, array $options = []): SaleReturn
    {
        if (empty($returnItems)) {
            throw new \InvalidArgumentException(__('return.errors.no_items'));
        }

        return DB::transaction(function () use ($sale, $returnItems, $options) {

            $date = $options['date'] ?? now()->toDateString();
            $policy = ReturnPolicy::discountPolicy();
            $refundAccount = $this->refundAccount($options);

            $return = SaleReturn::create([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'date' => $date,
                'reason' => $options['reason'] ?? null,
                'notes' => $options['notes'] ?? null,
                'deduction_type' => $options['deduction_type'] ?? 'none',
                'deduction_value' => round((float) ($options['deduction_value'] ?? 0), 2),
                'refund_account_id' => $refundAccount->id,
                'discount_policy' => $policy,
                'status' => 'completed',
                'created_by' => auth()->id(),
            ]);

            $return->update(['return_no' => 'SR'.str_pad((string) $return->id, 5, '0', STR_PAD_LEFT)]);

            $saleGross = $sale->gross();
            $revenueBack = 0.0;
            $costBack = 0.0;
            $discountBack = 0.0;

            foreach ($returnItems as $ri) {
                // Lock the source line so concurrent returns on the same item
                // are serialized and can't jointly exceed the sold quantity.
                $item = SaleItem::where('sale_id', $sale->id)
                    ->where('id', $ri['sale_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (float) $ri['qty'];
                $returnable = round((float) $item->qty - SaleReturnItem::alreadyReturnedQty($item->id), 3);

                if ($qty <= 0 || $qty > $returnable + self::EPSILON) {
                    throw new \InvalidArgumentException(__('return.errors.exceeds_returnable', [
                        'returnable' => $returnable,
                    ]));
                }

                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'qty' => $qty,
                    'unit_price' => $item->unit_price,
                    'cost_price' => $item->cost_price,
                ]);

                // Goods back into stock at their ORIGINAL frozen cost.
                $this->inventory->stockIn(
                    product: $item->product,
                    qty: $qty,
                    unitCost: (float) $item->cost_price,
                    referenceType: 'SaleReturn',
                    referenceId: $sale->id,
                    date: $date,
                );

                $revenueBack += $qty * (float) $item->unit_price;
                $costBack += $qty * (float) $item->cost_price;

                if ($policy === 'proportional' && (float) $item->qty > 0) {
                    $p = $qty / (float) $item->qty;
                    $lineDiscountShare = $p * (float) $item->discount;
                    $billDiscountShare = $saleGross > 0
                        ? (float) $sale->discount * (($qty * (float) $item->unit_price) / $saleGross)
                        : 0.0;
                    $discountBack += $lineDiscountShare + $billDiscountShare;
                }
            }

            $revenueBack = round($revenueBack, 2);
            $costBack = round($costBack, 2);
            $discountBack = round(min($discountBack, $revenueBack), 2);

            $deduction = $this->deduction($return, $revenueBack);
            if ($deduction > $revenueBack - $discountBack + self::EPSILON) {
                throw new \InvalidArgumentException(__('return.errors.deduction_exceeds'));
            }

            $netRefundDue = round($revenueBack - $discountBack - $deduction, 2);

            $refund = round((float) ($options['refund_amount'] ?? $netRefundDue), 2);
            if ($refund > $netRefundDue + self::EPSILON) {
                throw new \InvalidArgumentException(__('return.errors.refund_exceeds'));
            }
            if ($refund < 0) {
                $refund = 0.0;
            }
            $reduceReceivable = round($netRefundDue - $refund, 2);

            // --- Entry 1: reverse revenue, pay refund / clear due / keep deduction ---
            $revenueLines = [
                ['account_id' => $this->account(self::REVENUE_CODE)->id, 'debit' => $revenueBack, 'credit' => 0],
            ];
            if ($discountBack > 0) {
                $revenueLines[] = ['account_id' => $this->account(self::DISCOUNT_CODE)->id, 'debit' => 0, 'credit' => $discountBack];
            }
            if ($refund > 0) {
                $revenueLines[] = ['account_id' => $refundAccount->id, 'debit' => 0, 'credit' => $refund];
            }
            if ($reduceReceivable > 0) {
                $revenueLines[] = ['account_id' => $this->account(self::RECEIVABLE_CODE)->id, 'debit' => 0, 'credit' => $reduceReceivable];
            }
            if ($deduction > 0) {
                $revenueLines[] = ['account_id' => $this->account(self::DEDUCTION_CODE)->id, 'debit' => 0, 'credit' => $deduction];
            }

            $revenueEntry = $this->ledger->post(
                date: $date,
                referenceType: 'SaleReturn',
                referenceId: $sale->id,
                description: __('return.entry.revenue', ['no' => $return->return_no, 'invoice' => $sale->invoice_no ?? '#'.$sale->id]),
                lines: $revenueLines,
            );

            // --- Entry 2: reverse COGS ---
            $cogsEntry = null;
            if ($costBack > self::EPSILON) {
                $cogsEntry = $this->ledger->post(
                    date: $date,
                    referenceType: 'SaleReturnCOGS',
                    referenceId: $sale->id,
                    description: __('return.entry.cogs', ['no' => $return->return_no, 'invoice' => $sale->invoice_no ?? '#'.$sale->id]),
                    lines: [
                        ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => $costBack, 'credit' => 0],
                        ['account_id' => $this->account(self::COGS_CODE)->id, 'debit' => 0, 'credit' => $costBack],
                    ],
                );
            }

            $return->update([
                'revenue_entry_id' => $revenueEntry->id,
                'cogs_entry_id' => $cogsEntry?->id,
            ]);

            return $return->fresh(['items']);
        });
    }

    /**
     * Cancel a completed return: reverse both journal entries, pull the
     * restocked goods back out, and mark the document cancelled. Nothing is
     * ever deleted — the reversal chain and compensating movement are the
     * audit trail. Cancelling frees the returned qty for a future return.
     */
    public function cancel(SaleReturn $return, string $reason): SaleReturn
    {
        if ($return->status !== 'completed') {
            throw new \RuntimeException(__('return.errors.not_cancellable'));
        }

        return DB::transaction(function () use ($return, $reason) {

            $return->loadMissing(['items.product', 'revenueEntry.lines', 'cogsEntry.lines']);

            if ($return->revenueEntry) {
                $this->ledger->reverse($return->revenueEntry, $reason);
            }
            if ($return->cogsEntry) {
                $this->ledger->reverse($return->cogsEntry, $reason);
            }

            // Remove the units we had restocked (compensating movement).
            foreach ($return->items as $item) {
                $this->inventory->stockOut(
                    product: $item->product,
                    qty: (float) $item->qty,
                    referenceType: 'SaleReturnCancel',
                    referenceId: $return->sale_id,
                    date: now()->toDateString(),
                );
            }

            $return->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);

            return $return->fresh(['items']);
        });
    }

    private function deduction(SaleReturn $return, float $revenueBack): float
    {
        $value = (float) $return->deduction_value;

        return match ($return->deduction_type) {
            'fixed' => round(min($value, $revenueBack), 2),
            'percent' => round($revenueBack * $value / 100, 2),
            default => 0.0,
        };
    }

    private function refundAccount(array $options): Account
    {
        if (! empty($options['refund_account_id'])) {
            return Account::findOrFail($options['refund_account_id']);
        }

        return $this->account(self::CASH_CODE);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
