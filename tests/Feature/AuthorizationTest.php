<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Tests\TestCase;

/**
 * The role matrix from requirements §3.1 / NFR-07: the salesperson may create
 * sales but must never reach purchases, reports or cost/profit.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Lock the opening period so sale/purchase routes are reachable.
        $locker = User::factory()->create();
        app(PeriodLockService::class)->lockOpening($locker->id);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_salesperson_can_reach_sale_but_not_purchase_or_reports(): void
    {
        $sales = $this->userWithRole('salesperson');

        $this->actingAs($sales)->get('/sales/create')->assertOk();
        $this->actingAs($sales)->get('/purchases/create')->assertForbidden();
        $this->actingAs($sales)->get('/reports/trial-balance')->assertForbidden();
        $this->actingAs($sales)->get('/reports/profit-loss')->assertForbidden();
        $this->actingAs($sales)->get('/products/create')->assertForbidden();
    }

    public function test_salesperson_cannot_see_cost(): void
    {
        $sales = $this->userWithRole('salesperson');

        $this->assertFalse($sales->can('cost.view'));
        $this->assertFalse($sales->can('report.view'));
        $this->assertTrue($sales->can('sale.create'));
    }

    public function test_accountant_can_reach_reports_and_purchase_but_not_opening(): void
    {
        $acc = $this->userWithRole('accountant');

        $this->actingAs($acc)->get('/reports/trial-balance')->assertOk();
        $this->actingAs($acc)->get('/purchases/create')->assertOk();
        $this->assertTrue($acc->can('cost.view'));

        // opening.manage and entry.delete are owner-only.
        $this->actingAs($acc)->get('/opening')->assertForbidden();
        $this->assertFalse($acc->can('entry.delete'));
    }

    public function test_owner_can_reach_everything(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get('/opening')->assertOk();
        $this->actingAs($owner)->get('/products/create')->assertOk();
        $this->actingAs($owner)->get('/purchases/create')->assertOk();
        $this->actingAs($owner)->get('/reports/trial-balance')->assertOk();
        $this->assertTrue($owner->can('entry.delete'));
        $this->assertTrue($owner->can('cost.view'));
    }
}
