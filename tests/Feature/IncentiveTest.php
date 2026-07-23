<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Incentive\Services\IncentiveService;
use Modules\Incentive\Services\RebateService;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * Discounts (line + bill), incentives (income/expense) and rebates
 * (cost reduction) — each posting to the right account, books balanced.
 */
class IncentiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function balance(string $code): float
    {
        return $this->ledger()->balance(Account::where('code', $code)->first());
    }

    private function productWithStock(float $qty, float $cost, float $price): Product
    {
        return app(ProductService::class)->create([
            'name' => 'পণ্য '.uniqid(), 'unit' => 'pcs',
            'cost_price' => $cost, 'sale_price' => $price,
            'opening_qty' => $qty, 'opening_cost' => $cost,
        ]);
    }

    public function test_line_and_bill_discount_both_post_to_discount_account(): void
    {
        $product = $this->productWithStock(100, 40, 55);

        // Gross 20×55 = 1100; line discount 100 + bill discount 50 = 150 total.
        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [
                ['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55, 'discount' => 100],
            ],
            'discount' => 50,
            'paid_amount' => 950,   // net 1100 − 150 = 950
        ]);

        $this->assertEqualsWithDelta(1100, $this->balance('4010'), 0.01);   // gross revenue
        // 4020 is contra-revenue: a 150 debit shows as −150 in income direction.
        $this->assertEqualsWithDelta(-150, $this->balance('4020'), 0.01);
        $this->assertEqualsWithDelta(950, $this->balance('1010'), 0.01);    // cash collected
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_incentive_received_is_income(): void
    {
        app(IncentiveService::class)->receive([
            'amount' => 2000, 'date' => '2026-08-06',
        ]);

        $this->assertEqualsWithDelta(2000, $this->balance('4030'), 0.01);   // incentive income
        $this->assertEqualsWithDelta(2000, $this->balance('1010'), 0.01);   // cash up
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_incentive_paid_is_expense(): void
    {
        // Seed some cash first via an incentive received, then pay one out.
        app(IncentiveService::class)->receive(['amount' => 5000, 'date' => '2026-08-06']);

        app(IncentiveService::class)->pay([
            'amount' => 1200, 'date' => '2026-08-06',
        ]);

        $this->assertEqualsWithDelta(1200, $this->balance('5100'), 0.01);   // incentive expense
        $this->assertEqualsWithDelta(3800, $this->balance('1010'), 0.01);   // 5000 − 1200
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_rebate_reduces_inventory_cost_not_income(): void
    {
        $product = $this->productWithStock(100, 40, 55);   // stock value 4000

        // Supplier rebate of 400 → cost per unit drops 40 → 36.
        app(RebateService::class)->applyToProduct($product, 400, [
            'date' => '2026-08-06',
        ]);

        $product->refresh();

        $this->assertEqualsWithDelta(36, (float) $product->cost_price, 0.0001);
        $this->assertEqualsWithDelta(3600, $this->balance('1040'), 0.01);   // inventory reduced
        $this->assertEqualsWithDelta($product->stockValue(), $this->balance('1040'), 0.01);
        $this->assertEqualsWithDelta(400, $this->balance('1010'), 0.01);    // cash received

        // No incentive income recorded — a rebate is a cost reduction.
        $this->assertEqualsWithDelta(0, $this->balance('4030'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_rebate_beyond_stock_value_is_rejected(): void
    {
        $product = $this->productWithStock(10, 40, 55);   // value 400

        $this->expectException(\InvalidArgumentException::class);

        app(RebateService::class)->applyToProduct($product, 500, []);
    }
}
