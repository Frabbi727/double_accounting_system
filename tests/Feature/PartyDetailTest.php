<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\SupplierService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\PaymentService;
use Tests\TestCase;

/**
 * Customer/supplier due shown in the master list is the live ledger due (0 once
 * settled, never the frozen opening), and the full history stays reachable from
 * the detail page even after every due is cleared.
 */
class PartyDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $this->actingAs($owner);
    }

    private function openCash(float $amount): void
    {
        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: $amount,
            date: config('shop.cutoff_date'), source: $cash,
        );
    }

    public function test_customer_due_is_live_and_history_survives_settlement(): void
    {
        $reports = app(ReportService::class);

        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        // Before any payment, the live due equals the opening.
        $this->assertEqualsWithDelta(5000, $reports->partyDue('customer', $customer->id), 0.01);

        // Clear the whole due.
        app(PaymentService::class)->receiveFromCustomer($customer, ['amount' => 5000, 'date' => '2026-08-06']);

        // Live due is now 0 — NOT the frozen opening (5000).
        $this->assertEqualsWithDelta(0, $reports->partyDue('customer', $customer->id), 0.01);
        $this->assertEqualsWithDelta(5000, $customer->openingBalance(), 0.01); // opening is unchanged...

        // ...but the statement still carries the full history: opening + the receipt.
        $statement = $reports->partyStatement('customer', $customer->id);
        $this->assertEqualsWithDelta(5000, $statement['opening'], 0.01);
        $this->assertEqualsWithDelta(0, $statement['closing'], 0.01);
        $this->assertCount(1, $statement['rows']);
        $this->assertSame('PaymentIn', $statement['rows'][0]['reference_type']);
        $this->assertSame($customer->id, (int) $statement['rows'][0]['reference_id']);

        // The list still lists the customer (with 0 due) and the detail page opens.
        $this->get(route('customers.index'))->assertOk()->assertSee('করিম');
        $this->get(route('customers.show', $customer))->assertOk()->assertSee('করিম');
    }

    public function test_supplier_due_is_live_and_detail_page_renders(): void
    {
        $reports = app(ReportService::class);
        $this->openCash(20000);

        $supplier = app(SupplierService::class)->create([
            'name' => 'ABC Traders',
            'opening_items' => [['amount' => 8000, 'original_date' => '2026-06-10']],
        ]);

        $this->assertEqualsWithDelta(8000, $reports->partyDue('supplier', $supplier->id), 0.01);

        app(PaymentService::class)->payToSupplier($supplier, ['amount' => 8000, 'date' => '2026-08-06']);

        $this->assertEqualsWithDelta(0, $reports->partyDue('supplier', $supplier->id), 0.01);

        $statement = $reports->partyStatement('supplier', $supplier->id);
        $this->assertEqualsWithDelta(0, $statement['closing'], 0.01);
        $this->assertSame('PaymentOut', $statement['rows'][0]['reference_type']);

        $this->get(route('suppliers.index'))->assertOk()->assertSee('ABC Traders');
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('ABC Traders');
    }
}
