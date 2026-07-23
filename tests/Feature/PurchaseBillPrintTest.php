<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Money;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PurchaseService;
use Tests\TestCase;

/**
 * The printable purchase bill is the cost-side document (unit cost, goods
 * value, landed cost). Its route is gated on purchase.create, so the
 * salesperson — who has no such permission — is naturally kept out.
 */
class PurchaseBillPrintTest extends TestCase
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

    private function makePurchase(): Purchase
    {
        $product = app(ProductService::class)->create([
            'name' => 'লাক্স সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);
        app(PeriodLockService::class)->lockOpening(User::factory()->create()->id);

        // 10 @ 42 = 420 goods + 80 landed = 500 total; paid 300, due 200.
        return app(PurchaseService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 10, 'unit_cost' => 42]],
            'landed_cost' => 80,
            'paid_amount' => 300,
        ]);
    }

    public function test_bill_shows_products_costs_and_total(): void
    {
        $purchase = $this->makePurchase();
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->get(route('purchases.print', $purchase));

        $response->assertOk();
        $response->assertSee('লাক্স সাবান');
        $response->assertSee(Money::taka(420), false);   // goods value
        $response->assertSee(Money::taka(500), false);   // total
        $response->assertSee(Money::taka(200), false);   // due
    }

    public function test_user_without_purchase_permission_cannot_print(): void
    {
        $purchase = $this->makePurchase();

        // Salesperson has sale.create but not purchase.create.
        $this->actingAs($this->userWithRole('salesperson'))
            ->get(route('purchases.print', $purchase))
            ->assertForbidden();

        // A user with no role at all is also blocked.
        $this->actingAs(User::factory()->create())
            ->get(route('purchases.print', $purchase))
            ->assertForbidden();
    }

    public function test_receipt_format_renders(): void
    {
        $purchase = $this->makePurchase();

        $this->actingAs($this->userWithRole('owner'))
            ->get(route('purchases.print', ['purchase' => $purchase, 'format' => 'receipt']))
            ->assertOk()
            ->assertSee('লাক্স সাবান');
    }
}
