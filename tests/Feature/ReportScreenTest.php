<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\ExpenseService;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

class ReportScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        // Opening cash 50000, one product with low reorder, then lock.
        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: 50000,
            date: config('shop.cutoff_date'), source: $cash,
        );
        $this->product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40, 'reorder_level' => 95,
        ]);
        app(PeriodLockService::class)->lockOpening($this->owner->id);

        // A sale + an expense so reports have data.
        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $this->product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 1100,
        ]);
        app(ExpenseService::class)->create([
            'expense_account_id' => Account::where('code', '5020')->first()->id,
            'amount' => 500, 'date' => '2026-08-06',
        ]);
    }

    private Account $productHolder;

    public function test_all_report_screens_render_for_owner(): void
    {
        foreach ([
            'reports.index', 'reports.trial_balance', 'reports.balance_sheet',
            'reports.profit_loss', 'reports.day_book', 'reports.cash_book',
            'reports.stock', 'reports.low_stock', 'reports.customer_due',
            'reports.supplier_due', 'reports.aging', 'reports.product_profit',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_balance_sheet_balances(): void
    {
        $bs = app(ReportService::class)->balanceSheet();
        $this->assertTrue($bs['balanced']);
        $this->assertEqualsWithDelta(
            $bs['total_assets'],
            $bs['total_liabilities'] + $bs['total_equity'],
            0.01
        );
    }

    public function test_cash_book_closing_matches_ledger(): void
    {
        $report = app(ReportService::class)->cashBook('1010', '2026-07-01', '2026-08-31');
        $ledgerCash = app(LedgerService::class)->balance(Account::where('code', '1010')->first());

        $this->assertEqualsWithDelta($ledgerCash, $report['closing'], 0.01);
    }

    public function test_product_profit_matches_frozen_cost(): void
    {
        $report = app(ReportService::class)->productProfit();
        $row = collect($report['rows'])->firstWhere('name', 'সাবান');

        // 20 sold @ 55 = 1100 revenue; cost 20×40 = 800; profit 300.
        $this->assertEqualsWithDelta(1100, $row['revenue'], 0.01);
        $this->assertEqualsWithDelta(800, $row['cogs'], 0.01);
        $this->assertEqualsWithDelta(300, $row['profit'], 0.01);
    }

    public function test_low_stock_lists_products_below_reorder(): void
    {
        // Stock is 80 (100 − 20 sold), reorder 95 → low.
        $this->get(route('reports.low_stock'))->assertOk()->assertSee('সাবান');
    }

    public function test_salesperson_cannot_reach_reports(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('reports.product_profit'))->assertForbidden();
        $this->actingAs($sales)->get(route('reports.balance_sheet'))->assertForbidden();
    }
}
