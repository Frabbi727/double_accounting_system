<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Master\SupplierService;
use Tests\TestCase;

/**
 * Payments have no table of their own — each is a journal entry keyed to the
 * party (PaymentIn/PaymentOut). The list and printable voucher are built by
 * querying those entries, resolving the party, and reusing the ledger effect.
 * Both screens are gated by payment.manage (owner + accountant).
 */
class PaymentUiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private $customer;   // owes us 5000

    private $supplier;   // we owe them 5000

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');

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

    private function receive(float $amount): void
    {
        $this->actingAs($this->owner)->post('/payments', [
            'direction' => 'received', 'party_id' => $this->customer->id,
            'amount' => $amount, 'date' => '2026-08-06',
        ])->assertRedirect(route('payments.create'));
    }

    private function pay(float $amount): void
    {
        $this->actingAs($this->owner)->post('/payments', [
            'direction' => 'made', 'party_id' => $this->supplier->id,
            'amount' => $amount, 'date' => '2026-08-07',
        ])->assertRedirect(route('payments.create'));
    }

    public function test_list_shows_both_directions_with_party_names(): void
    {
        $this->receive(2000);   // adds cash so the supplier payment is affordable
        $this->pay(1000);

        $this->actingAs($this->owner)->get('/payments')
            ->assertOk()
            ->assertSee($this->customer->name)
            ->assertSee($this->supplier->name)
            ->assertSee(route('payments.show', JournalEntry::where('reference_type', 'PaymentIn')->first()));
    }

    public function test_receipt_voucher_shows_party_account_and_ledger(): void
    {
        $this->receive(2000);
        $entry = JournalEntry::where('reference_type', 'PaymentIn')->first();

        $this->actingAs($this->owner)->get("/payments/{$entry->id}")
            ->assertOk()
            ->assertSee($this->customer->name)   // party attribution
            ->assertSee('1010')                  // cash account debited
            ->assertSee('1030');                 // receivable control credited
    }

    public function test_supplier_payment_voucher_shows_payable_control(): void
    {
        $this->receive(2000);
        $this->pay(1000);
        $entry = JournalEntry::where('reference_type', 'PaymentOut')->first();

        $this->actingAs($this->owner)->get("/payments/{$entry->id}")
            ->assertOk()
            ->assertSee($this->supplier->name)
            ->assertSee('2010');                 // payable control debited
    }

    public function test_voucher_rejects_non_payment_entry(): void
    {
        // The opening journal entry is not a payment — it must 404 on this route.
        $opening = JournalEntry::whereNotIn('reference_type', ['PaymentIn', 'PaymentOut'])->first();
        $this->assertNotNull($opening);

        $this->actingAs($this->owner)->get("/payments/{$opening->id}")->assertNotFound();
    }

    public function test_list_and_voucher_role_gating(): void
    {
        $this->receive(2000);
        $entry = JournalEntry::where('reference_type', 'PaymentIn')->first();

        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        // Accountant has payment.manage; salesperson does not.
        $this->actingAs($accountant)->get('/payments')->assertOk();
        $this->actingAs($accountant)->get("/payments/{$entry->id}")->assertOk();
        $this->actingAs($sales)->get('/payments')->assertForbidden();
        $this->actingAs($sales)->get("/payments/{$entry->id}")->assertForbidden();
    }
}
