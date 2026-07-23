<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\PaymentService;
use Modules\Sale\Services\SaleService;
use Tests\TestCase;

/**
 * A party statement is built straight from the ledger, so its closing balance
 * must always equal the party's slice of the receivable/payable control.
 */
class PartyStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        // Customer with a 1000 opening due, one product, then lock.
        $this->customer = app(CustomerService::class)->create([
            'name' => 'করিম স্টোর',
            'opening_items' => [['amount' => 1000, 'original_date' => '2026-05-01']],
        ]);
        $product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);
        app(PeriodLockService::class)->lockOpening($this->owner->id);

        // Sale of 1100 with 400 paid → 700 added to their due.
        app(SaleService::class)->create([
            'customer_id' => $this->customer->id,
            'date' => '2026-08-06',
            'items' => [['product_id' => $product->id, 'qty' => 20, 'unit_price' => 55]],
            'paid_amount' => 400,
        ]);
        // Later receipt of 300.
        app(PaymentService::class)->receiveFromCustomer($this->customer, [
            'amount' => 300, 'date' => '2026-08-10',
        ]);
    }

    public function test_closing_matches_control_account_slice(): void
    {
        $statement = app(ReportService::class)->partyStatement('customer', $this->customer->id);

        // 1000 opening + 700 invoice due − 300 receipt = 1400.
        $this->assertEqualsWithDelta(1000, $statement['opening'], 0.01);
        $this->assertEqualsWithDelta(1400, $statement['closing'], 0.01);
        $this->assertEqualsWithDelta(700, $statement['total_charge'], 0.01);
        $this->assertEqualsWithDelta(300, $statement['total_payment'], 0.01);

        // Two control movements: the invoice due and the receipt.
        $this->assertCount(2, $statement['rows']);
        $this->assertEqualsWithDelta(1400, end($statement['rows'])['balance'], 0.01);
    }

    public function test_closing_equals_this_customers_only_receivable(): void
    {
        // This customer is the only receivable holder, so the AR control equals
        // their statement closing.
        $control = app(LedgerService::class)->balance(Account::where('code', '1030')->first());
        $statement = app(ReportService::class)->partyStatement('customer', $this->customer->id);

        $this->assertEqualsWithDelta($control, $statement['closing'], 0.01);
    }

    public function test_screen_renders_and_is_report_gated(): void
    {
        $this->actingAs($this->owner)
            ->get(route('reports.party_statement', ['party' => 'customer', 'id' => $this->customer->id]))
            ->assertOk()
            ->assertSee('করিম স্টোর');

        $sales = User::factory()->create();
        $sales->assignRole('salesperson');
        $this->actingAs($sales)
            ->get(route('reports.party_statement'))
            ->assertForbidden();
    }
}
