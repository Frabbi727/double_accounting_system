<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ReturnPolicy;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\StockMovement;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Return\Models\SaleReturn;
use Modules\Return\Models\SaleReturnItem;
use Modules\Return\Services\ReturnService;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * Product Return Management (requirement §2): first-class return documents that
 * fix the cumulative-return bug, handle deductions + discount policy, keep the
 * books balanced, restock, and cancel cleanly.
 */
class ReturnManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
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

    /** @param array<string,mixed> $overrides */
    private function makeSale(Product $product, array $overrides = []): Sale
    {
        app(SaleService::class)->create(array_merge([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 1100,
        ], $overrides));

        return Sale::latest('id')->first();
    }

    private function returns(): ReturnService
    {
        return app(ReturnService::class);
    }

    public function test_return_number_and_document_are_created(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product);

        $return = $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07']);

        $this->assertMatchesRegularExpression('/^SR\d{5}$/', $return->return_no);
        $this->assertSame('completed', $return->status);
        $this->assertEqualsWithDelta(275, $return->returnedAmount(), 0.01);   // 5 × 55
        $this->assertSame($sale->id, $return->sale_id);
    }

    public function test_cumulative_return_guard_across_multiple_returns(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product, [
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55]],
            'paid_amount' => 550,
        ]);
        $itemId = $sale->items->first()->id;

        // First return of 6 succeeds.
        $this->returns()->create($sale, [['sale_item_id' => $itemId, 'qty' => 6]], ['date' => '2026-08-07']);

        // A second return of 5 would total 11 > 10 sold — rejected.
        try {
            $this->returns()->create($sale, [['sale_item_id' => $itemId, 'qty' => 5]], ['date' => '2026-08-07']);
            $this->fail('Expected the cumulative guard to reject an over-return.');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        // But 4 more (total 10) is fine.
        $this->returns()->create($sale, [['sale_item_id' => $itemId, 'qty' => 4]], ['date' => '2026-08-07']);

        $this->assertEqualsWithDelta(10, SaleReturnItem::alreadyReturnedQty($itemId), 0.001);
    }

    public function test_fixed_deduction_posts_to_4040_and_balances(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product);

        // Return 5 (revenueBack 275), keep a fixed 25 as a restocking charge.
        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07', 'deduction_type' => 'fixed', 'deduction_value' => 25]);

        // Deduction income 4040 = 25.
        $this->assertEqualsWithDelta(25, $this->balance('4040'), 0.01);
        // Net revenue 4010 = 1100 − 275 = 825.
        $this->assertEqualsWithDelta(825, $this->balance('4010'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_percent_deduction(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product);

        // Return 5 (revenueBack 275), keep 10% = 27.50.
        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07', 'deduction_type' => 'percent', 'deduction_value' => 10]);

        $this->assertEqualsWithDelta(27.5, $this->balance('4040'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_discount_policy_ignore_leaves_sales_discount_untouched(): void
    {
        Setting::put(ReturnPolicy::KEY, ReturnPolicy::IGNORE);

        $product = $this->productWithStock(100, 40, 100);
        // 10 @ 100 = 1000 gross, 100 bill discount, net 900 paid in full.
        $sale = $this->makeSale($product, [
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 100]],
            'discount' => 100,
            'paid_amount' => 900,
        ]);

        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07']);

        // 4020 balance is credit-minus-debit = -100 (unchanged by the return).
        $this->assertEqualsWithDelta(-100, $this->balance('4020'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_discount_policy_proportional_reverses_sales_discount(): void
    {
        Setting::put(ReturnPolicy::KEY, ReturnPolicy::PROPORTIONAL);

        $product = $this->productWithStock(100, 40, 100);
        $sale = $this->makeSale($product, [
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 100]],
            'discount' => 100,
            'paid_amount' => 900,
        ]);

        // Return 5: bill-discount share = 100 × (500/1000) = 50 reversed to 4020.
        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07']);

        // 4020: was -100, return credits 50 → -50.
        $this->assertEqualsWithDelta(-50, $this->balance('4020'), 0.01);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_refund_via_bank_and_over_refund_rejected(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product);
        $bank = Account::where('code', '1021')->first();

        // Refund 5 × 55 = 275 to the bank account.
        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07', 'refund_account_id' => $bank->id, 'refund_amount' => 275]);

        // Refund credits (draws down) the bank asset: 0 − 275 = −275.
        $this->assertEqualsWithDelta(-275, $this->balance('1021'), 0.01);

        // Refunding more than the returned value is rejected.
        $this->expectException(\InvalidArgumentException::class);
        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 1],
        ], ['date' => '2026-08-07', 'refund_amount' => 999]);
    }

    public function test_return_restocks_and_records_movement(): void
    {
        $product = $this->productWithStock(100, 40, 55);   // stock 100
        $sale = $this->makeSale($product);                  // sell 20 → 80

        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07']);

        $product->refresh();
        $this->assertEqualsWithDelta(85, $product->currentStock(), 0.001);   // 80 + 5
        $this->assertSame(1, StockMovement::where('reference_type', 'SaleReturn')
            ->where('reference_id', $sale->id)->count());
    }

    public function test_reduce_receivable_on_credit_sale(): void
    {
        $customer = Customer::create(['name' => 'রহিম', 'name_normalized' => 'রহিম']);
        $product = $this->productWithStock(100, 40, 55);
        // Credit sale: 10 @ 55 = 550, paid 0 → receivable 550.
        $sale = $this->makeSale($product, [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55]],
            'paid_amount' => 0,
        ]);
        $this->assertEqualsWithDelta(550, $this->balance('1030'), 0.01);

        // Return 4 with no cash refund → lowers the customer's due by 220.
        $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 4],
        ], ['date' => '2026-08-07', 'refund_amount' => 0]);

        $this->assertEqualsWithDelta(330, $this->balance('1030'), 0.01);   // 550 − 220
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_cancel_reverses_entries_restores_stock_and_frees_qty(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product, [
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55]],
            'paid_amount' => 550,
        ]);
        $itemId = $sale->items->first()->id;

        $return = $this->returns()->create($sale, [['sale_item_id' => $itemId, 'qty' => 5]], ['date' => '2026-08-07']);
        $product->refresh();
        $this->assertEqualsWithDelta(95, $product->currentStock(), 0.001);   // 90 + 5

        $this->returns()->cancel($return->fresh(), 'ভুল এন্ট্রি');

        $product->refresh();
        $return->refresh();
        $this->assertSame('cancelled', $return->status);
        $this->assertEqualsWithDelta(90, $product->currentStock(), 0.001);   // restock removed
        $this->assertEqualsWithDelta(550, $this->balance('4010'), 0.01);     // revenue back to full
        $this->ledger()->assertLedgerBalanced();

        // Cancelled qty is freed, so the same 5 can be returned again.
        $this->assertEqualsWithDelta(0, SaleReturnItem::alreadyReturnedQty($itemId), 0.001);
        $this->returns()->create($sale, [['sale_item_id' => $itemId, 'qty' => 5]], ['date' => '2026-08-08']);
        $this->assertEqualsWithDelta(5, SaleReturnItem::alreadyReturnedQty($itemId), 0.001);
    }

    public function test_index_create_and_show_screens_render(): void
    {
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product);
        $return = $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07']);

        app(PeriodLockService::class)->lockOpening(User::factory()->create()->id);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->actingAs($owner)->get(route('returns.index'))->assertOk()->assertSee($return->return_no);
        $this->actingAs($owner)->get(route('returns.create', ['sale_id' => $sale->id]))->assertOk();
        $this->actingAs($owner)->get(route('returns.show', $return))->assertOk()
            ->assertSee($return->return_no);
    }

    public function test_cancel_is_owner_only(): void
    {
        // Build a completed return as a fixture.
        $product = $this->productWithStock(100, 40, 55);
        $sale = $this->makeSale($product);
        $return = $this->returns()->create($sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 5],
        ], ['date' => '2026-08-07']);

        app(PeriodLockService::class)->lockOpening(User::factory()->create()->id);

        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');
        $this->actingAs($accountant)
            ->post(route('returns.cancel', $return), ['cancel_reason' => 'x'])
            ->assertForbidden();

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner)
            ->post(route('returns.cancel', $return), ['cancel_reason' => 'ঠিক করা হলো'])
            ->assertRedirect(route('returns.show', $return));

        $this->assertSame('cancelled', $return->fresh()->status);
    }
}
