<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Purchase\Services\PurchaseService;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * The Sale module proves stock OUT, COGS, receivables and the cost freeze —
 * always leaving the books balanced.
 */
class SaleTest extends TestCase
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

    public function test_cash_sale_posts_revenue_and_cogs_and_reduces_stock(): void
    {
        $product = $this->productWithStock(100, 40, 55);

        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [
                ['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55],
            ],
            'paid_amount' => 550,
        ]);

        $product->refresh();

        // Stock down to 90.
        $this->assertEqualsWithDelta(90, $product->currentStock(), 0.001);

        // Revenue 550 (income → credit positive), cash +550.
        $this->assertEqualsWithDelta(550, $this->balance('4010'), 0.01);
        $this->assertEqualsWithDelta(550, $this->balance('1010'), 0.01);

        // COGS 10×40 = 400; inventory reduced from 4000 to 3600.
        $this->assertEqualsWithDelta(400, $this->balance('5010'), 0.01);
        $this->assertEqualsWithDelta(3600, $this->balance('1040'), 0.01);
        $this->assertEqualsWithDelta($product->stockValue(), $this->balance('1040'), 0.01);

        $this->ledger()->assertLedgerBalanced();
    }

    public function test_credit_sale_with_discount_records_receivable(): void
    {
        $product = $this->productWithStock(100, 40, 55);

        // Gross 550, discount 50, net 500, paid 200 → receivable 300.
        // A credit sale must name the customer (guard against orphaned dues).
        $customer = Customer::create(['name' => 'ক্রেতা']);
        app(SaleService::class)->create([
            'customer_id' => $customer->id,
            'date' => '2026-08-06',
            'items' => [
                ['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55],
            ],
            'discount' => 50,
            'paid_amount' => 200,
        ]);

        $this->assertEqualsWithDelta(550, $this->balance('4010'), 0.01);   // gross revenue
        $this->assertEqualsWithDelta(200, $this->balance('1010'), 0.01);   // cash paid
        $this->assertEqualsWithDelta(300, $this->balance('1030'), 0.01);   // receivable
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_cost_is_frozen_at_sale_time(): void
    {
        // Opening 100 @ 40, then buy 100 @ 60 → weighted average = 50.
        $product = $this->productWithStock(100, 40, 80);
        app(PurchaseService::class)->create([
            'date' => '2026-08-05',
            'items' => [['product_id' => $product->id, 'qty' => 100, 'unit_cost' => 60]],
            'paid_amount' => 6000,
        ]);
        $product->refresh();
        $this->assertEqualsWithDelta(50, (float) $product->cost_price, 0.0001);

        // Sell 10 — cost frozen at 50 → COGS 500.
        $sale = app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 80]],
            'paid_amount' => 800,
        ]);

        $this->assertEqualsWithDelta(50, (float) $sale->items->first()->cost_price, 0.0001);
        $this->assertEqualsWithDelta(500, $this->balance('5010'), 0.01);

        // Buy more at a higher cost AFTER the sale — historical COGS must not move.
        app(PurchaseService::class)->create([
            'date' => '2026-08-07',
            'items' => [['product_id' => $product->id, 'qty' => 100, 'unit_cost' => 100]],
            'paid_amount' => 10000,
        ]);

        $this->assertEqualsWithDelta(500, $this->balance('5010'), 0.01);
        $this->assertEqualsWithDelta(50, (float) Sale::first()->items->first()->cost_price, 0.0001);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_sale_beyond_available_stock_is_rejected(): void
    {
        $product = $this->productWithStock(5, 40, 55);

        $this->expectException(\RuntimeException::class);

        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55]],
            'paid_amount' => 0,
        ]);
    }

    public function test_credit_sale_without_a_customer_is_rejected(): void
    {
        $product = $this->productWithStock(100, 40, 55);

        // Unpaid balance with no customer would orphan the receivable.
        $this->expectException(\InvalidArgumentException::class);

        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55]],
            'paid_amount' => 0,
        ]);
    }
}
