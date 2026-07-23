<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\ExpenseService;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * The reports must always be internally consistent — Balance Sheet balances,
 * P&L equals the profit folded into equity, and derived stock/aging figures
 * agree with the ledger.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function openCash(float $amount): void
    {
        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: $amount,
            date: config('shop.cutoff_date'), source: $cash,
        );
    }

    public function test_balance_sheet_balances_after_activity(): void
    {
        $this->openCash(50000);

        $product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);

        // A profitable sale and an expense to move income & expense accounts.
        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 1100,
        ]);
        app(ExpenseService::class)->create([
            'expense_code' => '5020', 'amount' => 500, 'date' => '2026-08-06',
        ]);

        $bs = app(ReportService::class)->balanceSheet();

        $this->assertTrue($bs['balanced'], 'ব্যালেন্স শিট মিলছে না');
        $this->assertEqualsWithDelta(
            $bs['total_assets'],
            $bs['total_liabilities'] + $bs['total_equity'],
            0.01
        );
    }

    public function test_profit_and_loss_matches_balance_sheet_profit(): void
    {
        $this->openCash(50000);
        $product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);

        // Sell 20 @ 55 (revenue 1100, COGS 800 → gross profit 300), expense 100.
        app(SaleService::class)->create([
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 1100,
        ]);
        app(ExpenseService::class)->create([
            'expense_code' => '5020', 'amount' => 100, 'date' => '2026-08-06',
        ]);

        $pnl = app(ReportService::class)->profitAndLoss();

        // Revenue 1100 − (COGS 800 + expense 100) = 200
        $this->assertEqualsWithDelta(1100, $pnl['total_income'], 0.01);
        $this->assertEqualsWithDelta(900, $pnl['total_expense'], 0.01);
        $this->assertEqualsWithDelta(200, $pnl['net_profit'], 0.01);

        $bs = app(ReportService::class)->balanceSheet();
        $this->assertEqualsWithDelta($pnl['net_profit'], $bs['net_profit'], 0.01);
    }

    public function test_day_book_lists_balanced_entries(): void
    {
        $this->openCash(50000);

        $book = app(ReportService::class)->dayBook(config('shop.cutoff_date'));

        $this->assertNotEmpty($book);
        foreach ($book as $entry) {
            $debit = array_sum(array_column($entry['lines'], 'debit'));
            $credit = array_sum(array_column($entry['lines'], 'credit'));
            $this->assertEqualsWithDelta($debit, $credit, 0.01);
        }
    }

    public function test_stock_report_value_matches_inventory_ledger(): void
    {
        app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 50, 'opening_cost' => 40,
        ]);
        app(ProductService::class)->create([
            'name' => 'শ্যাম্পু', 'unit' => 'pcs',
            'cost_price' => 95, 'sale_price' => 120,
            'opening_qty' => 30, 'opening_cost' => 95,
        ]);

        $stock = app(ReportService::class)->stock();
        $ledgerInventory = app(LedgerService::class)
            ->balance(Account::where('code', '1040')->first());

        $this->assertEqualsWithDelta($ledgerInventory, $stock['total_value'], 0.01);
    }

    public function test_aging_buckets_receivables_by_age(): void
    {
        // Cut-off is 2026-07-31. original_date drives the bucket.
        app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [
                ['amount' => 1000, 'original_date' => '2026-07-20'],   // ~11 days → 0-30
                ['amount' => 2000, 'original_date' => '2026-05-01'],   // ~91 days → 90+
            ],
        ]);

        $aging = app(ReportService::class)->aging('customer', config('shop.cutoff_date'));

        $this->assertEqualsWithDelta(3000, $aging['total'], 0.01);
        $this->assertEqualsWithDelta(1000, $aging['buckets']['0-30'], 0.01);
        $this->assertEqualsWithDelta(2000, $aging['buckets']['90+'], 0.01);

        // Subsidiary total must equal the AR control account.
        $control = app(LedgerService::class)
            ->balance(Account::where('code', '1030')->first());
        $subsidiary = Customer::all()->sum(fn (Customer $c) => $c->openingBalance());
        $this->assertEqualsWithDelta($subsidiary, $control, 0.01);
    }
}
