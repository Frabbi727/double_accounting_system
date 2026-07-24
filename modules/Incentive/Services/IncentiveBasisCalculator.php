<?php

namespace Modules\Incentive\Services;

use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Reporting\ReportService;

/**
 * Turns an incentive/rebate "basis" into a concrete money amount.
 *
 *   fixed                → the amount is entered directly.
 *   pct_of_due           → rate % of the party's current outstanding due.
 *   pct_of_invoice       → rate % of one sale/purchase document's total.
 *   pct_of_product_value → rate % of a product's on-hand stock value.
 *   pct_of_sales         → "sell %": rate % of period turnover with the party.
 *
 * Every base is derived from the ledger (via ReportService) or live stock, so
 * nothing here is cached or second-guessed. Returns both the base it worked
 * from (stored for audit) and the final rounded amount.
 */
class IncentiveBasisCalculator
{
    public function __construct(
        private ReportService $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{base_amount: ?float, amount: float}
     */
    public function compute(array $data): array
    {
        $basis = $data['basis'] ?? 'fixed';

        if ($basis === 'fixed') {
            $amount = round((float) ($data['amount'] ?? 0), 2);
            $this->assertPositive($amount);

            return ['base_amount' => null, 'amount' => $amount];
        }

        $rate = round((float) ($data['rate'] ?? 0), 2);
        if ($rate <= 0) {
            throw new \InvalidArgumentException(__('incentive.errors.rate_positive'));
        }

        $base = round($this->base($basis, $data), 2);
        if ($base <= 0) {
            throw new \InvalidArgumentException(__('incentive.errors.base_zero'));
        }

        $amount = round($rate / 100 * $base, 2);
        $this->assertPositive($amount);

        return ['base_amount' => $base, 'amount' => $amount];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function base(string $basis, array $data): float
    {
        return match ($basis) {
            'pct_of_due' => $this->reports->partyDue(
                $data['party_type'] ?? 'customer',
                (int) ($data['party_id'] ?? 0),
            ),
            'pct_of_invoice' => $this->reports->documentTotal(
                $data['ref_doc_type'] ?? 'Sale',
                (int) ($data['ref_doc_id'] ?? 0),
            ),
            'pct_of_product_value' => $this->productValue((int) ($data['product_id'] ?? 0)),
            'pct_of_sales' => $this->reports->partyTurnover(
                $data['party_type'] ?? 'customer',
                (int) ($data['party_id'] ?? 0),
                $data['period_from'] ?? now()->toDateString(),
                $data['period_to'] ?? now()->toDateString(),
            ),
            default => throw new \InvalidArgumentException(__('incentive.errors.unknown_basis')),
        };
    }

    private function productValue(int $productId): float
    {
        $product = Product::findOrFail($productId);

        return round($product->currentStock() * (float) $product->cost_price, 2);
    }

    private function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('incentive.errors.amount_positive'));
        }
    }
}
