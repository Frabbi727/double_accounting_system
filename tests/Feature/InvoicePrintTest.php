<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * The printable invoice shows the revenue side only — never cost or profit
 * (NFR-07) — so even the salesperson can print it.
 */
class InvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeSale(): Sale
    {
        $product = app(ProductService::class)->create([
            'name' => 'লাক্স সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);
        app(PeriodLockService::class)->lockOpening(User::factory()->create()->id);

        return app(SaleService::class)->create([
            'customer_id' => Customer::create(['name' => 'ক্রেতা'])->id,
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_price' => 55]],
            'paid_amount' => 400,
        ]);
    }

    public function test_invoice_shows_products_and_net_but_not_cost(): void
    {
        $sale = $this->makeSale();
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->get(route('sales.print', $sale));

        $response->assertOk();
        $response->assertSee('লাক্স সাবান');
        // Net = gross 550 − 0 = 550; due = 550 − 400 = 150. Bengali digits.
        $response->assertSee(Money::taka(550), false);

        // The frozen cost (40) and profit must never appear on the invoice.
        $response->assertDontSee('৪০.০০');   // cost price in Bengali
        $response->assertDontSee('cost');
        $response->assertDontSee('profit');
    }

    public function test_salesperson_can_print_invoice(): void
    {
        $sale = $this->makeSale();
        $sales = $this->userWithRole('salesperson');

        $this->actingAs($sales)->get(route('sales.print', $sale))->assertOk();
    }

    public function test_user_without_sale_permission_cannot_print(): void
    {
        $sale = $this->makeSale();
        $nobody = User::factory()->create();   // no role

        $this->actingAs($nobody)->get(route('sales.print', $sale))->assertForbidden();
    }

    public function test_receipt_format_renders(): void
    {
        $sale = $this->makeSale();
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)
            ->get(route('sales.print', ['sale' => $sale, 'format' => 'receipt']))
            ->assertOk()
            ->assertSee('লাক্স সাবান');
    }
}
