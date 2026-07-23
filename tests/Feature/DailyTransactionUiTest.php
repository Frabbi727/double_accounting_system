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
use Modules\Accounting\Services\Master\CustomerService;
use Tests\TestCase;

/**
 * The daily-transaction screens (expense, payment, transfer, returns) post
 * through the existing services and keep the books balanced, while respecting
 * the role matrix.
 */
class DailyTransactionUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Lock the opening so daily-transaction routes become reachable. */
    private function lockOpening(): void
    {
        app(PeriodLockService::class)->lockOpening(User::factory()->create()->id);
    }

    private function balance(string $code): float
    {
        return app(LedgerService::class)->balance(Account::where('code', $code)->first());
    }

    public function test_accountant_records_expense_and_books_balance(): void
    {
        $acc = $this->userWithRole('accountant');
        $this->lockOpening();

        $this->actingAs($acc)->post('/expenses', [
            'expense_account_id' => Account::where('code', '5020')->first()->id, // shop rent
            'amount' => 3000,
            'date' => '2026-08-06',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(3000, $this->balance('5020'), 0.01);
        $this->assertEqualsWithDelta(-3000, $this->balance('1010'), 0.01); // cash went negative (no opening cash)
        app(LedgerService::class)->assertLedgerBalanced();
    }

    public function test_customer_payment_reduces_receivable(): void
    {
        $owner = $this->userWithRole('owner');

        $customer = app(CustomerService::class)->create([
            'name' => 'করিম',
            'opening_items' => [['amount' => 5000, 'original_date' => '2026-03-15']],
        ]);
        $this->lockOpening();

        $this->actingAs($owner)->post('/payments', [
            'direction' => 'received',
            'party_id' => $customer->id,
            'amount' => 2000,
            'date' => '2026-08-06',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(3000, $this->balance('1030'), 0.01);
        $this->assertEqualsWithDelta(2000, $this->balance('1010'), 0.01);
        app(LedgerService::class)->assertLedgerBalanced();
    }

    public function test_transfer_between_accounts_and_same_account_rejected(): void
    {
        $owner = $this->userWithRole('owner');

        // Seed some cash.
        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: 20000,
            date: config('shop.cutoff_date'), source: $cash,
        );
        $bank = Account::where('code', '1021')->first();
        $this->lockOpening();

        $this->actingAs($owner)->post('/transfers', [
            'from_account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'amount' => 5000,
            'date' => '2026-08-06',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(15000, $this->balance('1010'), 0.01);
        $this->assertEqualsWithDelta(5000, $this->balance('1021'), 0.01);

        // Same account is a validation error.
        $this->actingAs($owner)->post('/transfers', [
            'from_account_id' => $cash->id,
            'to_account_id' => $cash->id,
            'amount' => 100,
            'date' => '2026-08-06',
        ])->assertSessionHasErrors('to_account_id');
    }

    public function test_role_gating_on_daily_transactions(): void
    {
        $this->lockOpening();
        $sales = $this->userWithRole('salesperson');
        $acc = $this->userWithRole('accountant');

        // Salesperson can't reach expenses, payments or returns.
        $this->actingAs($sales)->get('/expenses/create')->assertForbidden();
        $this->actingAs($sales)->get('/payments/create')->assertForbidden();
        $this->actingAs($sales)->get('/returns/sale')->assertForbidden();

        // Accountant can do expenses/payments but not returns (owner-only).
        $this->actingAs($acc)->get('/expenses/create')->assertOk();
        $this->actingAs($acc)->get('/payments/create')->assertOk();
        $this->actingAs($acc)->get('/returns/sale')->assertForbidden();

        // Owner can reach returns and stock loss.
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner)->get('/returns/sale')->assertOk();
        $this->actingAs($owner)->get('/returns/purchase')->assertOk();
        $this->actingAs($owner)->get('/stock-loss')->assertOk();
    }
}
