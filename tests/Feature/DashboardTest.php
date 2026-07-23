<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: 50000,
            date: config('shop.cutoff_date'), source: $cash,
        );
        // Reorder 95 so the product goes low after selling 20 of 100.
        $product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40, 'reorder_level' => 95,
        ]);
        app(PeriodLockService::class)->lockOpening($owner->id);

        // Dated after the cutoff (opening period is locked up to the cutoff).
        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 1100,
        ]);
    }

    public function test_owner_sees_full_dashboard(): void
    {
        $owner = User::role('owner')->first();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('ui.dashboard.month_sales'))
            ->assertSee(__('ui.dashboard.month_profit'))     // cost.view
            ->assertSee(__('ui.dashboard.recent_activity'))  // report.view
            ->assertSee('সাবান');                            // low-stock list
    }

    public function test_salesperson_dashboard_hides_profit_and_activity(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('ui.dashboard.month_profit'))
            ->assertDontSee(__('ui.dashboard.recent_activity'));
    }
}
