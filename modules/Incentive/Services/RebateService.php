<?php

namespace Modules\Incentive\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;

/**
 * Rebate — a discount that comes back AFTER the purchase (FR-53). It is NOT
 * income: it reduces the cost of the goods bought.
 *
 *   Debit   Cash/Bank (received)   or reduce 2010 Payable
 *   Credit  1040 Inventory         (lowers the value of stock on hand)
 *
 * The rebate is applied to a specific product still on hand: its weighted-
 * average cost is reduced so that stock value drops by exactly the rebate,
 * keeping the inventory ledger equal to the summed stock value.
 */
class RebateService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const INVENTORY_CODE = '1040';

    private const PAYABLE_CODE = '2010';

    public function __construct(
        private LedgerService $ledger,
    ) {}

    /**
     * @param  array{date?:string, reduce_payable?:bool, account_id?:int, notes?:string}  $options
     */
    public function applyToProduct(Product $product, float $amount, array $options = []): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('incentive.errors.amount_positive'));
        }

        DB::transaction(function () use ($product, $amount, $options) {

            $qty = $product->currentStock();
            if ($qty <= self::EPSILON) {
                // Nothing on hand to lower the cost of.
                throw new \RuntimeException(__('incentive.errors.rebate_no_stock', ['product' => $product->name]));
            }

            $currentValue = round($qty * (float) $product->cost_price, 2);
            if ($amount > $currentValue + self::EPSILON) {
                throw new \InvalidArgumentException(__('incentive.errors.rebate_exceeds_value'));
            }

            // Lower the weighted-average cost so stock value drops by the rebate.
            $newCost = ($currentValue - $amount) / $qty;
            $product->update(['cost_price' => $newCost]);

            $date = $options['date'] ?? now()->toDateString();

            // Debit side: cash received, or reduce what we still owe.
            $debitAccount = ($options['reduce_payable'] ?? false)
                ? $this->account(self::PAYABLE_CODE)
                : $this->cashOrBank($options);

            $this->ledger->post(
                date: $date,
                referenceType: 'Rebate',
                referenceId: $product->id,
                description: $options['notes'] ?? __('incentive.rebate_description', ['product' => $product->name]),
                lines: [
                    ['account_id' => $debitAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => 0, 'credit' => $amount],
                ],
            );
        });
    }

    private function cashOrBank(array $options): Account
    {
        if (! empty($options['account_id'])) {
            return Account::findOrFail($options['account_id']);
        }

        return $this->account(self::CASH_CODE);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
