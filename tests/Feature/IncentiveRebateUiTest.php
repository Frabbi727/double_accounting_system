<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\ProductService;
use Modules\Accounting\Services\Master\SupplierService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Incentive\Models\PartyIncentive;
use Tests\TestCase;

/**
 * The incentive & rebate screens now attribute every event to a party and can
 * settle it either in cash or against that party's due. Incentives are
 * owner+accountant (payment.manage); rebate is owner-only (entry.delete)
 * because it re-values inventory.
 */
class IncentiveRebateUiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    private Customer $customer;   // owes us 5000

    private Supplier $supplier;   // we owe them 5000

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

        // 100 @ 40 = 4000 stock value on hand.
        $this->product = app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 100, 'opening_cost' => 40,
        ]);

        // Opening dues (created during the opening period, before the lock).
        $this->customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);
        $this->supplier = app(SupplierService::class)->create([
            'name' => 'রহিম ট্রেডার্স',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);

        app(PeriodLockService::class)->lockOpening($this->owner->id);
    }

    private function ledger(string $code): float
    {
        return app(LedgerService::class)->balance(Account::where('code', $code)->first());
    }

    private function due(string $party, int $id): float
    {
        return app(ReportService::class)->partyDue($party, $id);
    }

    /** Seed some cash so a cash payout is possible (received cash is money in). */
    private function fundCash(float $amount): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'received', 'settle_mode' => 'cash', 'basis' => 'fixed',
            'amount' => $amount, 'date' => '2026-08-06',
        ]);
    }

    public function test_incentive_received_from_supplier_in_cash_is_income(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'received', 'settle_mode' => 'cash', 'basis' => 'fixed',
            'party_id' => $this->supplier->id, 'amount' => 1000, 'date' => '2026-08-06',
        ])->assertRedirect(route('incentives.index'));

        $this->assertEqualsWithDelta(1000, $this->ledger('4030'), 0.01);
        $this->assertEqualsWithDelta(1000, $this->ledger('1010'), 0.01);

        $row = PartyIncentive::first();
        $this->assertSame('supplier', $row->party_type);
        $this->assertSame($this->supplier->id, $row->party_id);
        $this->assertEqualsWithDelta(1000, (float) $row->amount, 0.01);
    }

    public function test_incentive_received_settled_against_supplier_due(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'received', 'settle_mode' => 'due', 'basis' => 'fixed',
            'party_id' => $this->supplier->id, 'amount' => 1000, 'date' => '2026-08-06',
        ])->assertRedirect(route('incentives.index'));

        // Income booked, and what we owe the supplier drops by exactly 1000.
        $this->assertEqualsWithDelta(1000, $this->ledger('4030'), 0.01);
        $this->assertEqualsWithDelta(4000, $this->due('supplier', $this->supplier->id), 0.01);
        // No cash moved.
        $this->assertEqualsWithDelta(0, $this->ledger('1010'), 0.01);
    }

    public function test_incentive_given_to_customer_settled_against_their_due(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'given', 'settle_mode' => 'due', 'basis' => 'fixed',
            'party_id' => $this->customer->id, 'amount' => 1500, 'date' => '2026-08-06',
        ])->assertRedirect(route('incentives.index'));

        $this->assertEqualsWithDelta(1500, $this->ledger('5100'), 0.01);       // expense
        $this->assertEqualsWithDelta(3500, $this->due('customer', $this->customer->id), 0.01);
    }

    public function test_incentive_percentage_of_due_computes_amount(): void
    {
        // 10% of the supplier's 5000 due = 500, settled against the due.
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'received', 'settle_mode' => 'due', 'basis' => 'pct_of_due',
            'party_id' => $this->supplier->id, 'rate' => 10, 'date' => '2026-08-06',
        ])->assertRedirect(route('incentives.index'));

        $row = PartyIncentive::first();
        $this->assertEqualsWithDelta(5000, (float) $row->base_amount, 0.01);
        $this->assertEqualsWithDelta(500, (float) $row->amount, 0.01);
        $this->assertEqualsWithDelta(4500, $this->due('supplier', $this->supplier->id), 0.01);
    }

    public function test_over_settle_against_due_is_rejected(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'received', 'settle_mode' => 'due', 'basis' => 'fixed',
            'party_id' => $this->supplier->id, 'amount' => 6000, 'date' => '2026-08-06',
        ])->assertSessionHasErrors('amount');

        // Nothing posted; the due is untouched.
        $this->assertSame(0, PartyIncentive::count());
        $this->assertEqualsWithDelta(5000, $this->due('supplier', $this->supplier->id), 0.01);
    }

    public function test_incentive_given_in_cash_without_funds_is_rejected(): void
    {
        $this->actingAs($this->owner)->post('/incentives', [
            'direction' => 'given', 'settle_mode' => 'cash', 'basis' => 'fixed',
            'party_id' => $this->customer->id, 'amount' => 600, 'date' => '2026-08-06',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, PartyIncentive::count());
    }

    public function test_rebate_cash_lowers_product_cost_and_inventory(): void
    {
        $this->actingAs($this->owner)->post('/rebates', [
            'product_id' => $this->product->id, 'settle_mode' => 'cash', 'basis' => 'fixed',
            'amount' => 400, 'date' => '2026-08-06',
        ])->assertRedirect(route('rebates.index'));

        $this->assertEqualsWithDelta(36, (float) $this->product->fresh()->cost_price, 0.001);
        $this->assertEqualsWithDelta(3600, $this->ledger('1040'), 0.01);
    }

    public function test_rebate_percentage_of_product_value_settled_against_supplier_due(): void
    {
        // 10% of 4000 stock value = 400, netted against the supplier's payable.
        $this->actingAs($this->owner)->post('/rebates', [
            'product_id' => $this->product->id, 'settle_mode' => 'due', 'basis' => 'pct_of_product_value',
            'party_id' => $this->supplier->id, 'rate' => 10, 'date' => '2026-08-06',
        ])->assertRedirect(route('rebates.index'));

        $this->assertEqualsWithDelta(36, (float) $this->product->fresh()->cost_price, 0.001);
        $this->assertEqualsWithDelta(3600, $this->ledger('1040'), 0.01);
        // Supplier payable reduced by the rebate.
        $this->assertEqualsWithDelta(4600, $this->due('supplier', $this->supplier->id), 0.01);
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

        // Owner reaches both, including the new rebate list.
        $this->actingAs($this->owner)->get('/incentives')->assertOk();
        $this->actingAs($this->owner)->get('/rebates')->assertOk();
        $this->actingAs($this->owner)->get('/rebates/create')->assertOk();
    }
}
