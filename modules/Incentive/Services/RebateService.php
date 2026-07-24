<?php

namespace Modules\Incentive\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Incentive\Models\PartyIncentive;

/**
 * Rebate — a discount that comes back AFTER the purchase (FR-53). It is NOT
 * income: it reduces the cost of the goods bought.
 *
 *   Credit  1040 Inventory  (lowers the value of stock on hand — always)
 *   Debit   Cash/Bank (received in cash)   → reference 'Rebate', ref = product
 *      or   2010 Payable  (netted against a supplier's due) → 'RebatePayable', ref = supplier
 *
 * The rebate is applied to a specific product still on hand: its weighted-
 * average cost is reduced so that stock value drops by exactly the rebate,
 * keeping the inventory ledger equal to the summed stock value. When settled
 * against a supplier's due, the entry carries the supplier id so it flows into
 * that supplier's statement/aging; every event is logged to party_incentives.
 */
class RebateService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const INVENTORY_CODE = '1040';

    private const PAYABLE_CODE = '2010';

    public function __construct(
        private LedgerService $ledger,
        private ReportService $reports,
        private IncentiveBasisCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $data  product_id, party_id (supplier, optional),
     *   settle_mode (cash|due), account_id, basis, rate, amount, date, notes,
     *   ref_doc_type, ref_doc_id, period_from, period_to
     */
    public function record(array $data): PartyIncentive
    {
        $product = Product::findOrFail($data['product_id']);
        $settleMode = $data['settle_mode'] ?? 'cash';
        $partyId = ! empty($data['party_id']) ? (int) $data['party_id'] : null;
        $date = $data['date'] ?? now()->toDateString();

        if ($settleMode === 'due' && ! $partyId) {
            throw new \InvalidArgumentException(__('incentive.errors.due_needs_party'));
        }

        ['base_amount' => $base, 'amount' => $amount] = $this->calculator->compute(
            ['party_type' => 'supplier', 'product_id' => $product->id] + $data
        );

        return DB::transaction(function () use ($product, $partyId, $settleMode, $amount, $base, $date, $data) {

            $qty = $product->currentStock();
            if ($qty <= self::EPSILON) {
                // Nothing on hand to lower the cost of.
                throw new \RuntimeException(__('incentive.errors.rebate_no_stock', ['product' => $product->name]));
            }

            $currentValue = round($qty * (float) $product->cost_price, 2);
            if ($amount > $currentValue + self::EPSILON) {
                throw new \InvalidArgumentException(__('incentive.errors.rebate_exceeds_value'));
            }

            if ($settleMode === 'due') {
                $this->guardAgainstOverSettle((int) $partyId, $amount);
            }

            // Lower the weighted-average cost so stock value drops by the rebate.
            $product->update(['cost_price' => ($currentValue - $amount) / $qty]);

            // Debit side + how the entry is attributed. Cash rebate stays keyed to
            // the product (historical 'Rebate'); a due-settled rebate is keyed to
            // the supplier via the distinct 'RebatePayable' type.
            if ($settleMode === 'due') {
                $debit = $this->account(self::PAYABLE_CODE);
                $referenceType = 'RebatePayable';
                $referenceId = $partyId;
            } else {
                $debit = $this->cashOrBank($data);
                $referenceType = 'Rebate';
                $referenceId = $product->id;
            }

            $entry = $this->ledger->post(
                date: $date,
                referenceType: $referenceType,
                referenceId: $referenceId,
                description: $data['notes'] ?? __('incentive.rebate_description', ['product' => $product->name]),
                lines: [
                    ['account_id' => $debit->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $this->account(self::INVENTORY_CODE)->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            return PartyIncentive::create([
                'kind' => 'rebate',
                'direction' => 'received',
                'party_type' => $partyId ? 'supplier' : null,
                'party_id' => $partyId,
                'basis' => $data['basis'] ?? 'fixed',
                'rate' => ($data['basis'] ?? 'fixed') === 'fixed' ? null : round((float) ($data['rate'] ?? 0), 2),
                'base_amount' => $base,
                'amount' => $amount,
                'product_id' => $product->id,
                'ref_doc_type' => $data['ref_doc_type'] ?? null,
                'ref_doc_id' => $data['ref_doc_id'] ?? null,
                'settle_mode' => $settleMode,
                'settle_account_id' => $settleMode === 'cash' ? $debit->id : null,
                'period_from' => $data['period_from'] ?? null,
                'period_to' => $data['period_to'] ?? null,
                'date' => $date,
                'notes' => $data['notes'] ?? null,
                'journal_entry_id' => $entry->id,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Backward-compatible shorthand: apply a flat cash rebate to a product.
     * Kept for callers/tests predating the party/basis flow.
     *
     * @param  array{date?:string, reduce_payable?:bool, party_id?:int, account_id?:int, notes?:string}  $options
     */
    public function applyToProduct(Product $product, float $amount, array $options = []): PartyIncentive
    {
        return $this->record([
            'product_id' => $product->id,
            'amount' => $amount,
            'basis' => 'fixed',
            'settle_mode' => ($options['reduce_payable'] ?? false) ? 'due' : 'cash',
            'party_id' => $options['party_id'] ?? null,
            'account_id' => $options['account_id'] ?? null,
            'date' => $options['date'] ?? null,
            'notes' => $options['notes'] ?? null,
        ]);
    }

    /** A due-settled rebate may not exceed what we currently owe the supplier. */
    private function guardAgainstOverSettle(int $supplierId, float $amount): void
    {
        $due = $this->reports->partyDue('supplier', $supplierId);

        if ($amount > $due + self::EPSILON) {
            throw new \InvalidArgumentException(__('incentive.errors.exceeds_due', [
                'due' => Money::taka(max($due, 0)),
            ]));
        }
    }

    private function cashOrBank(array $data): Account
    {
        if (! empty($data['account_id'])) {
            return Account::findOrFail($data['account_id']);
        }

        return $this->account(self::CASH_CODE);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
