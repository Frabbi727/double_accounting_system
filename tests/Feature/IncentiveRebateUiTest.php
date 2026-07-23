<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Tests\TestCase;

/**
 * The incentive and rebate screens drive the existing Incentive/Rebate
 * services. Incentives are owner+accountant (payment.manage); rebate is
 * owner-only (entry.delete) because it re-values inventory.
 */
class IncentiveRebateUiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        // 100 @ 40 = 4000 stock value on hand, then lock.
        $this->product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);
        app(PeriodLockService::class)->lockOpening($this->owner->id);
    }

    private function ledger(string $code): float
    {
        return app(LedgerService::class)->balance(Account::where('code', $code)->first());
    }

    public function test_incentive_received_is_income(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'received', 'amount' => 1000, 'date' => '2026-08-06',
        ])->assertRedirect(route('incentives.index'));

        // 4030 Incentive Income credited; cash up by the same.
        $this->assertEqualsWithDelta(1000, $this->ledger('4030'), 0.01);
        $this->assertEqualsWithDelta(1000, $this->ledger('1010'), 0.01);
    }

    public function test_incentive_paid_is_expense(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'paid', 'amount' => 600, 'date' => '2026-08-06',
        ])->assertRedirect(route('incentives.index'));

        // 5100 Incentive Expense debited; cash down by the same.
        $this->assertEqualsWithDelta(600, $this->ledger('5100'), 0.01);
        $this->assertEqualsWithDelta(-600, $this->ledger('1010'), 0.01);
    }

    public function test_rebate_lowers_product_cost_and_inventory(): void
    {
        $this->actingAs($this->owner)->post('/rebates', [
            'product_id' => $this->product->id, 'amount' => 400, 'date' => '2026-08-06',
        ])->assertRedirect(route('rebates.create'));

        // (4000 − 400) / 100 = 36 new weighted-average cost.
        $this->assertEqualsWithDelta(36, (float) $this->product->fresh()->cost_price, 0.001);
        // Inventory 1040 dropped by exactly the rebate.
        $this->assertEqualsWithDelta(3600, $this->ledger('1040'), 0.01);
    }

    public function test_role_gating(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        // Accountant can reach incentives, but not the owner-only rebate.
        $this->actingAs($accountant)->get('/incentives/create')->assertOk();
        $this->actingAs($accountant)->get('/rebates/create')->assertForbidden();

        // Salesperson can reach neither.
        $this->actingAs($sales)->get('/incentives')->assertForbidden();
        $this->actingAs($sales)->get('/rebates/create')->assertForbidden();

        // Owner reaches both.
        $this->actingAs($this->owner)->get('/incentives')->assertOk();
        $this->actingAs($this->owner)->get('/rebates/create')->assertOk();
    }
}
