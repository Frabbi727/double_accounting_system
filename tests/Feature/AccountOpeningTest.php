<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Tests\TestCase;

/**
 * Setting / editing the opening balance of an existing account (the seeded
 * cash / bank / loan accounts that start at ৳0). Every change must keep the
 * ledger balanced and preserve a full audit trail.
 */
class AccountOpeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        return $user;
    }

    private function balance(string $code): float
    {
        return app(LedgerService::class)->balance(Account::code($code)->firstOrFail());
    }

    public function test_first_set_posts_a_balanced_opening_entry(): void
    {
        $owner = $this->owner();
        $cash = Account::code('1010')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('accounts.opening.update', $cash), ['amount' => 50000])
            ->assertRedirect(route('accounts.index'));

        // Dr Cash 50,000 / Cr Owner's Equity 50,000.
        $this->assertEqualsWithDelta(50000, $this->balance('1010'), 0.01);
        $this->assertEqualsWithDelta(50000, $this->balance('3010'), 0.01);
        app(LedgerService::class)->assertLedgerBalanced();
    }

    public function test_liability_opening_credits_the_loan(): void
    {
        $owner = $this->owner();
        $loan = Account::code('2020')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('accounts.opening.update', $loan), ['amount' => 100000]);

        // Cr Bank Loan 100,000 / Dr Owner's Equity 100,000 (a loan lowers net capital).
        $this->assertEqualsWithDelta(100000, $this->balance('2020'), 0.01);
        $this->assertEqualsWithDelta(-100000, $this->balance('3010'), 0.01);
        app(LedgerService::class)->assertLedgerBalanced();
    }

    public function test_edit_reverses_and_reposts_keeping_history(): void
    {
        $owner = $this->owner();
        $cash = Account::code('1010')->firstOrFail();

        $this->actingAs($owner)->post(route('accounts.opening.update', $cash), ['amount' => 50000]);
        $this->actingAs($owner)->post(route('accounts.opening.update', $cash), [
            'amount' => 60000,
            'reason' => 'গণনায় ভুল ছিল',
        ]);

        // Live balance reflects the corrected amount only.
        $this->assertEqualsWithDelta(60000, $this->balance('1010'), 0.01);

        // The original entry survives as a reversed record (append-only audit trail).
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'Opening']);
        $this->assertGreaterThanOrEqual(
            3, // original + its reversal + the reposted entry
            \Modules\Accounting\Models\JournalEntry::where('reference_type', 'Opening')->count()
        );
        app(LedgerService::class)->assertLedgerBalanced();
    }

    public function test_update_is_rejected_once_opening_is_locked(): void
    {
        $owner = $this->owner();
        $cash = Account::code('1010')->firstOrFail();

        // Seed a balanced opening then lock the period.
        $this->actingAs($owner)->post(route('accounts.opening.update', $cash), ['amount' => 50000]);
        app(PeriodLockService::class)->lockOpening($owner->id);

        $this->actingAs($owner)
            ->post(route('accounts.opening.update', $cash), ['amount' => 99999])
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('warning');

        // Balance unchanged — the guard held.
        $this->assertEqualsWithDelta(50000, $this->balance('1010'), 0.01);
    }

    public function test_edit_link_hidden_when_opening_locked(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('accounts.index'))
            ->assertOk()
            ->assertSee(__('ui.account.set_opening'));

        app(PeriodLockService::class)->lockOpening($owner->id);

        $this->actingAs($owner)->get(route('accounts.index'))
            ->assertOk()
            ->assertDontSee(__('ui.account.set_opening'));
    }
}
