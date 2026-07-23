<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\SupplierService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\ExpenseService;
use Modules\Finance\Services\PaymentService;
use Modules\Finance\Services\TransferService;
use Tests\TestCase;

/**
 * The per-account statement shows every movement — money in, money out, to
 * whom, and a running balance — and always reconciles to the ledger balance.
 */
class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    private function cash(): Account
    {
        return Account::where('code', '1010')->first();
    }

    private function openCash(float $amount): void
    {
        $cash = $this->cash();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: $amount,
            date: config('shop.cutoff_date'), source: $cash,
        );
    }

    public function test_statement_lists_movements_with_types_and_reconciles(): void
    {
        $this->openCash(50000);

        $supplier = app(SupplierService::class)->create([
            'name' => 'ABC',
            'opening_items' => [['amount' => 8000, 'original_date' => '2026-06-10']],
        ]);
        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        // A day of activity, all touching the cash account (1010).
        app(PaymentService::class)->payToSupplier($supplier, ['amount' => 3000, 'date' => '2026-08-06']);
        app(ExpenseService::class)->create(['expense_code' => '5020', 'amount' => 2000, 'date' => '2026-08-06']);
        app(PaymentService::class)->receiveFromCustomer($customer, ['amount' => 4000, 'date' => '2026-08-07']);
        app(TransferService::class)->transfer(
            from: $this->cash(),
            to: Account::where('code', '1021')->first(),
            data: ['amount' => 10000, 'date' => '2026-08-08'],
        );

        $report = app(ReportService::class)->accountStatement($this->cash(), '2026-08-01', '2026-08-31');

        // Opening = the 50000 seeded before the range.
        $this->assertEqualsWithDelta(50000, $report['opening'], 0.01);

        // Four movements, identified by their (locale-independent) reference type.
        $types = array_column($report['rows'], 'reference_type');
        $this->assertContains('PaymentOut', $types);
        $this->assertContains('Expense', $types);
        $this->assertContains('PaymentIn', $types);
        $this->assertContains('Transfer', $types);

        // Every row carries a human-readable, localized label (never blank, and
        // resolved — not left as the raw reference_type).
        foreach ($report['rows'] as $r) {
            $this->assertNotEmpty($r['type_label']);
            $this->assertNotSame($r['reference_type'], $r['type_label']);
        }

        // In/out are correct for a cash (asset) account.
        $this->assertEqualsWithDelta(4000, $report['total_in'], 0.01);          // only the receipt
        $this->assertEqualsWithDelta(15000, $report['total_out'], 0.01);        // 3000 + 2000 + 10000

        // Closing must equal the ledger balance for the same window — the invariant.
        $ledgerBalance = app(LedgerService::class)->balance($this->cash(), '2026-08-31');
        $this->assertEqualsWithDelta($ledgerBalance, $report['closing'], 0.01);
        $this->assertEqualsWithDelta(39000, $report['closing'], 0.01);          // 50000 + 4000 − 15000
    }

    public function test_statement_screen_renders_for_an_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->openCash(1000);

        $this->actingAs($owner)
            ->get(route('accounts.statement', $this->cash()))
            ->assertOk()
            ->assertSee('1010');
    }

    public function test_statement_exports_to_csv_and_pdf(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->openCash(5000);

        $this->actingAs($owner)
            ->get(route('reports.export.account_statement', ['account' => $this->cash(), 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($owner)
            ->get(route('reports.export.account_statement', ['account' => $this->cash(), 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
