<?php

namespace Modules\Adjustment\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleItem;

/**
 * Records a sale return (customer brings goods back).
 *
 *   Debit   4010 Sales Revenue     (reverse the revenue at the sold price)
 *   Credit  Cash/Bank or 1030      (refund, or reduce their due)
 *   Debit   1040 Inventory         (goods back in, at the original frozen cost)
 *   Credit  5010 COGS              (reverse the cost)
 *
 * Goods return to stock at the SAME cost they left at (the frozen sale cost),
 * so the inventory ledger stays equal to the summed stock value and the COGS
 * reversal is exact. The original sale is never edited.
 */
class SaleReturnService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const RECEIVABLE_CODE = '1030';

    private const INVENTORY_CODE = '1040';

    private const REVENUE_CODE = '4010';

    private const COGS_CODE = '5010';

    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /**
     * @param  array<int, array{sale_item_id:int, qty:float}>  $returnItems
     * @param  array{date?:string, refund_amount?:float, refund_account_id?:int, notes?:string}  $options
     */
    public function returnSale(Sale $sale, array $returnItems, array $options = []): void
    {
        if (empty($returnItems)) {
            throw new \InvalidArgumentException(__('adjustment.errors.no_items'));
        }

        DB::transaction(function () use ($sale, $returnItems, $options) {

            $date = $options['date'] ?? now()->toDateString();

            $revenueBack = 0.0;
            $costBack = 0.0;

            foreach ($returnItems as $ri) {
                $item = SaleItem::where('sale_id', $sale->id)
                    ->where('id', $ri['sale_item_id'])
                    ->firstOrFail();

                $qty = (float) $ri['qty'];
                if ($qty <= 0 || $qty > (float) $item->qty + self::EPSILON) {
                    throw new \InvalidArgumentException(__('adjustment.errors.bad_qty'));
                }

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
            }

            $revenueBack = round($revenueBack, 2);
            $costBack = round($costBack, 2);

            $refund = round((float) ($options['refund_amount'] ?? 0), 2);
            if ($refund > $revenueBack + self::EPSILON) {
                throw new \InvalidArgumentException(__('adjustment.errors.refund_exceeds'));
            }
            $reduceReceivable = round($revenueBack - $refund, 2);

            // --- Entry 1: reverse revenue ---
            $revenueLines = [
                ['account_id' => $this->account(self::REVENUE_CODE)->id, 'debit' => $revenueBack, 'credit' => 0],
            ];
            if ($refund > 0) {
                $revenueLines[] = ['account_id' => $this->refundAccount($options)->id, 'debit' => 0, 'credit' => $refund];
            }
            if ($reduceReceivable > 0) {
                $revenueLines[] = ['account_id' => $this->account(self::RECEIVABLE_CODE)->id, 'debit' => 0, 'credit' => $reduceReceivable];
            }

            $this->ledger->post(
                date: $date,
                referenceType: 'SaleReturn',
                referenceId: $sale->id,
                description: __('adjustment.sale_return_description', ['invoice' => $sale->invoice_no ?? '#'.$sale->id]),
                lines: $revenueLines,
            );

            // --- Entry 2: reverse COGS ---
            if ($costBack > self::EPSILON) {
                $this->ledger->post(
                    date: $date,
                    referenceType: 'SaleReturnCOGS',
                    referenceId: $sale->id,
                    description: __('adjustment.sale_return_cogs_description', ['invoice' => $sale->invoice_no ?? '#'.$sale->id]),
                    lines: [
                        ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => $costBack, 'credit' => 0],
                        ['account_id' => $this->account(self::COGS_CODE)->id, 'debit' => 0, 'credit' => $costBack],
                    ],
                );
            }
        });
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
