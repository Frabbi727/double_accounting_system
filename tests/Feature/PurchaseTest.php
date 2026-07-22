<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Inventory\InventoryService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Purchase\Services\PurchaseService;
use Tests\TestCase;

/**
 * The Purchase module proves stock IN, weighted-average costing and the
 * inventory/payable ledger — always leaving the books balanced.
 */
class PurchaseTest extends TestCase
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

    public function test_purchase_updates_weighted_average_cost_and_stock(): void
    {
        // Opening: 50 pcs @ 40  (cost 40)
        $product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 50, 'opening_cost' => 40,
        ]);

        // Buy 50 more @ 44, pay 1000 in cash, rest on credit.
        app(PurchaseService::class)->create([
            'date' => '2026-08-05',
            'items' => [
                ['product_id' => $product->id, 'qty' => 50, 'unit_cost' => 44],
            ],
            'paid_amount' => 1000,
        ]);

        $product->refresh();

        // Weighted average: (50×40 + 50×44) / 100 = 42
        $this->assertEqualsWithDelta(42, (float) $product->cost_price, 0.0001);
        $this->assertEqualsWithDelta(100, $product->currentStock(), 0.001);

        // Inventory ledger must equal the summed stock value (100 × 42 = 4200).
        $this->assertEqualsWithDelta(4200, $this->balance('1040'), 0.01);
        $this->assertEqualsWithDelta($product->stockValue(), $this->balance('1040'), 0.01);

        // Payable = total (2200) − paid (1000) = 1200.
        $this->assertEqualsWithDelta(1200, $this->balance('2010'), 0.01);

        $this->ledger()->assertLedgerBalanced();
    }

    public function test_landed_cost_is_capitalized_and_apportioned(): void
    {
        $p1 = app(ProductService::class)->create([
            'name' => 'পণ্য-১', 'unit' => 'pcs', 'cost_price' => 0, 'sale_price' => 200,
        ]);
        $p2 = app(ProductService::class)->create([
            'name' => 'পণ্য-২', 'unit' => 'pcs', 'cost_price' => 0, 'sale_price' => 400,
        ]);

        // Goods 1000 + 2000 = 3000, landed 300, fully paid.
        app(PurchaseService::class)->create([
            'date' => '2026-08-05',
            'items' => [
                ['product_id' => $p1->id, 'qty' => 10, 'unit_cost' => 100],
                ['product_id' => $p2->id, 'qty' => 10, 'unit_cost' => 200],
            ],
            'landed_cost' => 300,
            'paid_amount' => 3300,
        ]);

        $p1->refresh();
        $p2->refresh();

        // Landed cost apportioned by value: p1 gets 100 (→ 110/unit), p2 gets 200 (→ 220/unit).
        $this->assertEqualsWithDelta(110, (float) $p1->cost_price, 0.0001);
        $this->assertEqualsWithDelta(220, (float) $p2->cost_price, 0.0001);

        // Inventory ledger = goods + landed = 3300 = summed stock value.
        $stockValue = Product::all()->sum(fn (Product $p) => $p->stockValue());
        $this->assertEqualsWithDelta(3300, $this->balance('1040'), 0.01);
        $this->assertEqualsWithDelta($stockValue, $this->balance('1040'), 0.01);

        // Fully paid → no payable.
        $this->assertEqualsWithDelta(0, $this->balance('2010'), 0.01);

        $this->ledger()->assertLedgerBalanced();
    }

    public function test_stock_out_beyond_available_is_rejected(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'চিনি', 'unit' => 'kg',
            'cost_price' => 60, 'sale_price' => 70,
            'opening_qty' => 10, 'opening_cost' => 60,
        ]);

        $this->expectException(\RuntimeException::class);

        // Only 10 in stock, try to issue 20 (negative stock disabled by config).
        app(InventoryService::class)->stockOut(
            product: $product,
            qty: 20,
            referenceType: 'Sale',
            referenceId: null,
            date: '2026-08-06',
        );
    }
}
