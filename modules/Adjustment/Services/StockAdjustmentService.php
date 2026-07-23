<?php

namespace Modules\Adjustment\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;

/**
 * Records stock loss / damage / theft.
 *
 *   Debit   5110 Stock Loss
 *   Credit  1040 Inventory
 *
 * Plus a negative stock_movements row of type 'adjustment'. The loss is valued
 * at the current weighted-average cost, so the inventory ledger stays equal to
 * the summed stock value.
 */
class StockAdjustmentService
{
    private const EPSILON = 0.005;

    private const INVENTORY_CODE = '1040';

    private const STOCK_LOSS_CODE = '5110';

    public function __construct(
        private LedgerService $ledger,
        private InventoryService $inventory,
    ) {}

    /**
     * @param  array{date?:string, reason?:string}  $options
     */
    public function recordLoss(Product $product, float $qty, array $options = []): void
    {
        if ($qty <= 0) {
            throw new \InvalidArgumentException(__('adjustment.errors.bad_qty'));
        }

        DB::transaction(function () use ($product, $qty, $options) {

            $date = $options['date'] ?? now()->toDateString();
            $value = round($qty * (float) $product->cost_price, 2);

            $this->inventory->adjustOut(
                product: $product,
                qty: $qty,
                referenceType: 'StockLoss',
                referenceId: $product->id,
                date: $date,
            );

            if ($value < self::EPSILON) {
                // Zero-value stock (fully written-down) — the movement is enough.
                return;
            }

            $this->ledger->post(
                date: $date,
                referenceType: 'StockLoss',
                referenceId: $product->id,
                description: $options['reason']
                    ?? __('adjustment.stock_loss_description', ['product' => $product->name]),
                lines: [
                    ['account_id' => $this->account(self::STOCK_LOSS_CODE)->id, 'debit' => $value, 'credit' => 0],
                    ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => 0, 'credit' => $value],
                ],
            );
        });
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
