<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;

/**
 * Records a purchase: stock IN for every line (weighted-average costing) plus
 * one balanced journal entry.
 *
 *   Debit   1040 Inventory      (goods value + landed cost)
 *   Credit  Cash/Bank           (amount paid now)
 *   Credit  2010 Payable        (amount left on credit)
 *
 * Landed cost is capitalized into inventory and apportioned across the lines
 * by value, so the inventory ledger always equals the summed stock value.
 * Everything happens in one transaction — stock and books move together.
 */
class PurchaseService
{
    private const CASH_CODE = '1010';

    private const INVENTORY_CODE = '1040';

    private const PAYABLE_CODE = '2010';

    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /**
     * Expected $data shape:
     *   supplier_id?, invoice_no?, date?, landed_cost?, paid_amount?,
     *   payment_account_id?, notes?,
     *   items => [ ['product_id'=>.., 'qty'=>.., 'unit_cost'=>..], ... ]
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new \InvalidArgumentException(__('purchase.errors.no_items'));
            }

            $date = $data['date'] ?? now()->toDateString();
            $landed = round((float) ($data['landed_cost'] ?? 0), 2);

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'invoice_no' => $data['invoice_no'] ?? null,
                'date' => $date,
                'landed_cost' => $landed,
                'paid_amount' => round((float) ($data['paid_amount'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Goods value, needed to apportion the landed cost.
            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += (float) $item['qty'] * (float) $item['unit_cost'];
            }
            $subtotal = round($subtotal, 2);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (float) $item['qty'];
                $cost = (float) $item['unit_cost'];

                if ($qty <= 0 || $cost <= 0) {
                    throw new \InvalidArgumentException(__('purchase.errors.line_positive'));
                }

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_cost' => $cost,
                ]);

                // Capitalize this line's share of landed cost into its unit cost,
                // so the weighted-average cost (and thus stock value) matches the
                // inventory debited below.
                $lineValue = $qty * $cost;
                $share = $subtotal > 0 ? $landed * ($lineValue / $subtotal) : 0.0;
                $effectiveUnitCost = ($lineValue + $share) / $qty;

                $this->inventory->stockIn(
                    product: $product,
                    qty: $qty,
                    unitCost: $effectiveUnitCost,
                    referenceType: 'Purchase',
                    referenceId: $purchase->id,
                    date: $date,
                );
            }

            $total = round($subtotal + $landed, 2);
            $paid = (float) $purchase->paid_amount;

            if ($paid > $total + 0.005) {
                throw new \InvalidArgumentException(__('purchase.errors.paid_exceeds_total'));
            }

            $due = round($total - $paid, 2);

            // Debit inventory for the full capitalized value; credit whatever was
            // paid and whatever remains on account. Only non-zero lines are posted.
            $lines = [
                ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => $total, 'credit' => 0],
            ];

            if ($paid > 0) {
                $lines[] = ['account_id' => $this->paymentAccount($data)->id, 'debit' => 0, 'credit' => $paid];
            }

            if ($due > 0) {
                $lines[] = ['account_id' => $this->account(self::PAYABLE_CODE)->id, 'debit' => 0, 'credit' => $due];
            }

            $this->ledger->post(
                date: $date,
                referenceType: 'Purchase',
                referenceId: $purchase->id,
                description: __('purchase.description', [
                    'invoice' => $purchase->invoice_no ?? '#'.$purchase->id,
                ]),
                lines: $lines,
            );

            return $purchase->fresh('items');
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
