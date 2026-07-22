<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Exceptions\OpeningAlreadyPostedException;
use Modules\Accounting\Exceptions\UnbalancedJournalException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Master\AccountService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Accounting\Services\Master\SupplierService;
use Tests\TestCase;

/**
 * These tests protect the one property the whole system depends on:
 * the ledger must balance after every single operation, in any order.
 */
class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    /** The core invariant. If this fails, nothing else matters. */
    public function test_ledger_balances_after_each_opening_entry_in_any_order(): void
    {
        $ledger = app(LedgerService::class);

        // Add masters one at a time, checking the ledger after every single one.
        app(AccountService::class)->create([
            'name' => 'ক্যাশ বক্স', 'type' => 'asset', 'subtype' => 'cash',
            'opening_balance' => 25000,
        ]);
        $ledger->assertLedgerBalanced();

        app(ProductService::class)->create([
            'name' => 'লাক্স সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 50, 'opening_cost' => 40,
        ]);
        $ledger->assertLedgerBalanced();

        app(CustomerService::class)->create([
            'name' => 'করিম ভাই',
            'opening_items' => [
                ['amount' => 5000, 'original_date' => '2026-03-15', 'reference' => 'INV-123'],
            ],
        ]);
        $ledger->assertLedgerBalanced();

        app(SupplierService::class)->create([
            'name' => 'ABC ট্রেডার্স',
            'opening_items' => [
                ['amount' => 45000, 'original_date' => '2026-06-10'],
            ],
        ]);
        $ledger->assertLedgerBalanced();

        $tb = $ledger->trialBalance();
        $this->assertTrue($tb['balanced']);
    }

    public function test_owners_equity_is_computed_correctly(): void
    {
        app(AccountService::class)->create([
            'name' => 'ক্যাশ', 'type' => 'asset', 'subtype' => 'cash',
            'opening_balance' => 25000,
        ]);

        app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        app(SupplierService::class)->create([
            'name' => 'ABC',
            'opening_items' => [['amount' => 20000, 'original_date' => '2026-06-10']],
        ]);

        // Assets 30,000 − Liabilities 20,000 = Equity 10,000
        $equity = app(LedgerService::class)->balance(
            Account::where('code', '3010')->first()
        );

        $this->assertEqualsWithDelta(10000, $equity, 0.01);
    }

    public function test_inventory_journal_matches_stock_movement_value(): void
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

        // Stock side
        $stockValue = Product::all()->sum(fn (Product $p) => $p->stockValue());

        // Ledger side
        $ledgerValue = app(LedgerService::class)->balance(
            Account::where('code', '1040')->first()
        );

        $this->assertEqualsWithDelta($stockValue, $ledgerValue, 0.01,
            'ইনভেন্টরি জার্নাল আর স্টক মুভমেন্টের মূল্য মিলছে না');
    }

    public function test_subsidiary_totals_match_control_accounts(): void
    {
        app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [
                ['amount' => 3000, 'original_date' => '2026-03-15'],
                ['amount' => 2000, 'original_date' => '2026-04-02'],
            ],
        ]);
        app(CustomerService::class)->create([
            'name' => 'রহিম',
            'opening_items' => [['amount' => 4000, 'original_date' => '2026-05-01']],
        ]);

        $subsidiary = Customer::all()->sum(fn (Customer $c) => $c->openingBalance());

        $control = app(LedgerService::class)->balance(
            Account::where('code', '1030')->first()
        );

        $this->assertEqualsWithDelta($subsidiary, $control, 0.01,
            'কাস্টমারদের মোট বাকি AR কন্ট্রোল অ্যাকাউন্টের সাথে মিলছে না');
    }

    public function test_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        app(LedgerService::class)->post(
            date: '2026-07-31',
            referenceType: 'Test',
            referenceId: null,
            description: 'ভুল এন্ট্রি',
            lines: [
                ['account_id' => Account::where('code', '1010')->first()->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => Account::where('code', '3010')->first()->id, 'debit' => 0,   'credit' => 90],
            ],
        );
    }

    public function test_stock_without_cost_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ProductService::class)->create([
            'name' => 'চিনি', 'unit' => 'kg',
            'cost_price' => 0, 'sale_price' => 60,
            'opening_qty' => 20, 'opening_cost' => 0,
        ]);
    }

    public function test_correction_reverses_instead_of_editing(): void
    {
        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        app(CustomerService::class)->correctOpening($customer, 7000, 'ভুল অংক দেওয়া হয়েছিল');

        // Original entry still exists, now marked as reversed.
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'Opening']);

        // Net AR balance reflects the corrected amount only.
        $ar = app(LedgerService::class)->balance(Account::where('code', '1030')->first());
        $this->assertEqualsWithDelta(7000, $ar, 0.01);

        app(LedgerService::class)->assertLedgerBalanced();
    }

    public function test_double_opening_for_same_record_is_rejected(): void
    {
        $this->expectException(OpeningAlreadyPostedException::class);

        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        // Attempting a second opening for the same customer must fail.
        app(OpeningEntryService::class)->post(
            account: app(OpeningEntryService::class)->receivableAccount(),
            amount: 1000,
            date: config('shop.cutoff_date'),
            source: $customer,
        );
    }
}
