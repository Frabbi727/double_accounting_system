<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Adjustment\Services\PurchaseReturnService;
use Modules\Adjustment\Services\SaleReturnService;
use Modules\Adjustment\Services\StockAdjustmentService;
use Modules\Purchase\Services\PurchaseService;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * Returns and adjustments must reverse the right accounts, bring stock back /
 * take it out correctly, and always leave the books balanced.
 */
class AdjustmentTest extends TestCase
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

    public function test_sale_return_reverses_revenue_cogs_and_restocks(): void
    {
        $product = $this->productWithStock(100, 40, 55);

        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 1100,
        ]);

        // Return 5 of the 20, cash refund.
        $sale = Sale::first();
        app(SaleReturnService::class)->returnSale($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07', 'refund_amount' => 275]);

        $product->refresh();

        // Stock back to 85 (100 − 20 + 5).
        $this->assertEqualsWithDelta(85, $product->currentStock(), 0.001);

        // Net revenue = 1100 − 275 = 825; net COGS = 800 − 200 = 600.
        $this->assertEqualsWithDelta(825, $this->balance('4010'), 0.01);
        $this->assertEqualsWithDelta(600, $this->balance('5010'), 0.01);

        // Inventory ledger still equals stock value.
        $this->assertEqualsWithDelta($product->stockValue(), $this->balance('1040'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_purchase_return_reduces_inventory_and_payable(): void
    {
        $product = $this->productWithStock(0, 0, 100);

        // Buy 50 @ 60 fully on credit → payable 3000, stock 50 @ 60.
        app(PurchaseService::class)->create([
            'date' => '2026-08-05',
            'items' => [['product_id' => $product->id, 'qty' => 50, 'unit_cost' => 60]],
            'paid_amount' => 0,
        ]);
        $this->assertEqualsWithDelta(3000, $this->balance('2010'), 0.01);

        // Return 10 to supplier, reducing what we owe.
        app(PurchaseReturnService::class)->returnPurchase(
            [['product_id' => $product->id, 'qty' => 10]],
            ['date' => '2026-08-06'],
        );

        $product->refresh();
        $this->assertEqualsWithDelta(40, $product->currentStock(), 0.001);
        $this->assertEqualsWithDelta(2400, $this->balance('2010'), 0.01);   // 3000 − 600
        $this->assertEqualsWithDelta($product->stockValue(), $this->balance('1040'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_stock_loss_writes_off_inventory(): void
    {
        $product = $this->productWithStock(50, 40, 55);

        // Lose 5 units → 5×40 = 200 to Stock Loss.
        app(StockAdjustmentService::class)->recordLoss($product, 5, [
            'date' => '2026-08-06', 'reason' => 'পানিতে নষ্ট',
        ]);

        $product->refresh();
        $this->assertEqualsWithDelta(45, $product->currentStock(), 0.001);
        $this->assertEqualsWithDelta(200, $this->balance('5110'), 0.01);
        $this->assertEqualsWithDelta(1800, $this->balance('1040'), 0.01);   // 2000 − 200
        $this->assertEqualsWithDelta($product->stockValue(), $this->balance('1040'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_returning_more_than_sold_is_rejected(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 5, 'unit_price' => 55]],
            'paid_amount' => 275,
        ]);

        $sale = Sale::first();

        $this->expectException(\InvalidArgumentException::class);

        app(SaleReturnService::class)->returnSale($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 10],   // sold only 5
        ], ['refund_amount' => 0]);
    }
}
