<?php

namespace Modules\Adjustment\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;

/**
 * Records a purchase return (we send goods back to a supplier).
 *
 *   Debit   Cash/Bank or 2010 Payable   (refund received, or reduce what we owe)
 *   Credit  1040 Inventory              (goods out, at current weighted-avg cost)
 *
 * Goods leave at the current weighted-average cost, so the inventory ledger
 * stays equal to the summed stock value. (Any difference between that and the
 * original invoice price is not tracked as a variance in this version.)
 */
class PurchaseReturnService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const INVENTORY_CODE = '1040';

    private const PAYABLE_CODE = '2010';

    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /**
     * @param  array<int, array{product_id:int, qty:float}>  $items
     * @param  array{date?:string, refund_amount?:float, refund_account_id?:int, reference_id?:int, notes?:string}  $options
     */
    public function returnPurchase(array $items, array $options = []): void
    {
        if (empty($items)) {
            throw new \InvalidArgumentException(__('adjustment.errors.no_items'));
        }

        DB::transaction(function () use ($items, $options) {

            $date = $options['date'] ?? now()->toDateString();
            $value = 0.0;

            foreach ($items as $it) {
                $product = Product::findOrFail($it['product_id']);
                $qty = (float) $it['qty'];
                if ($qty <= 0) {
                    throw new \InvalidArgumentException(__('adjustment.errors.bad_qty'));
                }

                // Value the goods leaving at the current weighted-average cost.
                $value += $qty * (float) $product->cost_price;

                $this->inventory->stockOut(
                    product: $product,
                    qty: $qty,
                    referenceType: 'PurchaseReturn',
                    referenceId: $options['reference_id'] ?? null,
                    date: $date,
                );
            }

            $value = round($value, 2);
            $refund = round((float) ($options['refund_amount'] ?? 0), 2);
            if ($refund > $value + self::EPSILON) {
                throw new \InvalidArgumentException(__('adjustment.errors.refund_exceeds'));
            }
            $reducePayable = round($value - $refund, 2);

            $lines = [];
            if ($refund > 0) {
                $lines[] = ['account_id' => $this->refundAccount($options)->id, 'debit' => $refund, 'credit' => 0];
            }
            if ($reducePayable > 0) {
                $lines[] = ['account_id' => $this->account(self::PAYABLE_CODE)->id, 'debit' => $reducePayable, 'credit' => 0];
            }
            $lines[] = ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => 0, 'credit' => $value];

            $this->ledger->post(
                date: $date,
                referenceType: 'PurchaseReturn',
                referenceId: $options['reference_id'] ?? null,
                description: $options['notes'] ?? __('adjustment.purchase_return_description'),
                lines: $lines,
            );
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
