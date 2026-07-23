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
use Modules\Finance\Services\ExpenseService;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create(['name' => 'মালিক']);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: 10000,
            date: config('shop.cutoff_date'), source: $cash,
        );
        app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 10, 'opening_cost' => 40,
        ]);
        app(PeriodLockService::class)->lockOpening($this->owner->id);

        app(ExpenseService::class)->create([
            'expense_account_id' => Account::where('code', '5020')->first()->id,
            'amount' => 500, 'date' => '2026-08-06',
        ]);
    }

    public function test_audit_log_lists_entries_with_creator(): void
    {
        $this->actingAs($this->owner)->get(route('reports.audit_log'))
            ->assertOk()
            ->assertSee('মালিক')                       // creator name
            ->assertSee(__('ui.report.audit_live'));   // status column
    }

    public function test_audit_log_filters_by_type(): void
    {
        // Filtering to Expense shows the expense but hides the opening entries.
        $response = $this->actingAs($this->owner)
            ->get(route('reports.audit_log', ['type' => 'Expense']))
            ->assertOk();

        $response->assertSee(__('ui.nav_more.expense'));
    }

    public function test_salesperson_cannot_reach_audit_log(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get(route('reports.audit_log'))->assertForbidden();
    }
}
