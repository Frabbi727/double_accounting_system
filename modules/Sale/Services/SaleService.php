<?php

namespace Modules\Sale\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleItem;

/**
 * Records a sale as TWO balanced journal entries (per docs/AGENT_INSTRUCTIONS),
 * plus a stock-out movement per line.
 *
 * Entry 1 — revenue:
 *   Debit   Cash/Bank            (amount paid now)
 *   Debit   1030 Receivable      (amount left on credit)
 *   Debit   4020 Sales Discount  (discount given, if any)
 *   Credit  4010 Sales Revenue   (gross total before discount)
 *
 * Entry 2 — cost of goods sold:
 *   Debit   5010 COGS            (Σ qty × frozen cost_price)
 *   Credit  1040 Inventory       (same amount)
 *
 * The line cost is frozen at sale time, so historical profit never shifts
 * when the product's weighted-average cost later changes.
 */
class SaleService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const RECEIVABLE_CODE = '1030';

    private const INVENTORY_CODE = '1040';

    private const REVENUE_CODE = '4010';

    private const DISCOUNT_CODE = '4020';

    private const COGS_CODE = '5010';

    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /**
     * Expected $data shape:
     *   customer_id?, invoice_no?, date?, discount?, paid_amount?,
     *   payment_account_id?, notes?,
     *   items => [ ['product_id'=>.., 'qty'=>.., 'unit_price'=>..], ... ]
     */
    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException(__('sale.errors.no_items'));
            }

            $date = $data['date'] ?? now()->toDateString();
            $billDiscount = round((float) ($data['discount'] ?? 0), 2);

            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_no' => $data['invoice_no'] ?? null,
                'date' => $date,
                'discount' => $billDiscount,
                'paid_amount' => round((float) ($data['paid_amount'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $gross = 0.0;
            $cogs = 0.0;
            $lineDiscount = 0.0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (float) $item['qty'];
                $price = (float) $item['unit_price'];
                $itemDiscount = round((float) ($item['discount'] ?? 0), 2);

                if ($qty <= 0 || $price < 0) {
                    throw new \InvalidArgumentException(__('sale.errors.line_invalid'));
                }
                if ($itemDiscount < 0 || $itemDiscount > $qty * $price + self::EPSILON) {
                    throw new \InvalidArgumentException(__('sale.errors.line_discount_invalid'));
                }

                // Freeze the cost NOW — copy the current weighted-average cost.
                $frozenCost = (float) $product->cost_price;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $price,
                    'discount' => $itemDiscount,
                    'cost_price' => $frozenCost,
                ]);

                // Issue stock (enforces availability unless negative stock is allowed).
                $this->inventory->stockOut(
                    product: $product,
                    qty: $qty,
                    referenceType: 'Sale',
                    referenceId: $sale->id,
                    date: $date,
                );

                $gross += $qty * $price;
                $cogs += $qty * $frozenCost;
                $lineDiscount += $itemDiscount;
            }

            $gross = round($gross, 2);
            $cogs = round($cogs, 2);

            // Total discount = per-line discounts + a whole-bill discount.
            // All of it debits 4020; revenue is still credited gross.
            $discount = round($billDiscount + $lineDiscount, 2);

            if ($gross <= self::EPSILON) {
                throw new \InvalidArgumentException(__('sale.errors.zero_revenue'));
            }
            if ($discount > $gross + self::EPSILON) {
                throw new \InvalidArgumentException(__('sale.errors.discount_exceeds'));
            }

            $net = round($gross - $discount, 2);
            $paid = (float) $sale->paid_amount;

            if ($paid > $net + self::EPSILON) {
                throw new \InvalidArgumentException(__('sale.errors.paid_exceeds'));
            }

            $receivable = round($net - $paid, 2);

            // A credit sale (unpaid balance) must name the customer, otherwise the
            // receivable posts to AR 1030 attributable to nobody — an orphan due.
            if ($receivable > self::EPSILON && empty($data['customer_id'])) {
                throw new \InvalidArgumentException(__('sale.errors.credit_needs_customer'));
            }

            // --- Entry 1: revenue (only non-zero lines) ---
            $revenueLines = [];
            if ($paid > 0) {
                $revenueLines[] = ['account_id' => $this->paymentAccount($data)->id, 'debit' => $paid, 'credit' => 0];
            }
            if ($receivable > 0) {
                $revenueLines[] = ['account_id' => $this->account(self::RECEIVABLE_CODE)->id, 'debit' => $receivable, 'credit' => 0];
            }
            if ($discount > 0) {
                $revenueLines[] = ['account_id' => $this->account(self::DISCOUNT_CODE)->id, 'debit' => $discount, 'credit' => 0];
            }
            $revenueLines[] = ['account_id' => $this->account(self::REVENUE_CODE)->id, 'debit' => 0, 'credit' => $gross];

            $this->ledger->post(
                date: $date,
                referenceType: 'Sale',
                referenceId: $sale->id,
                description: __('sale.description', ['invoice' => $sale->invoice_no ?? '#'.$sale->id]),
                lines: $revenueLines,
            );

            // --- Entry 2: cost of goods sold ---
            if ($cogs > self::EPSILON) {
                $this->ledger->post(
                    date: $date,
                    referenceType: 'SaleCOGS',
                    referenceId: $sale->id,
                    description: __('sale.cogs_description', ['invoice' => $sale->invoice_no ?? '#'.$sale->id]),
                    lines: [
                        ['account_id' => $this->account(self::COGS_CODE)->id, 'debit' => $cogs, 'credit' => 0],
                        ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => 0, 'credit' => $cogs],
                    ],
                );
            }

            return $sale->fresh('items');
        });
    }

    private function paymentAccount(array $data): Account
    {
        if (! empty($data['payment_account_id'])) {
            return Account::findOrFail($data['payment_account_id']);
        }

        return $this->account(self::CASH_CODE);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
