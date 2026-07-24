<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Tests\TestCase;

/**
 * FR-18 / §6.3: daily transactions (sales) are blocked until the opening
 * period is locked.
 */
class OpeningLockUiTest extends TestCase
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

    /**
     * Force the books out of balance by inserting a raw one-sided line straight
     * into the immutable ledger (bypassing LedgerService, which would reject
     * it). This is the only way to exercise the not-balanced hard blocker.
     */
    private function unbalanceTheBooks(int $userId): void
    {
        $cash = Account::code('1010')->firstOrFail();

        $entryId = DB::table('journal_entries')->insertGetId([
            'date' => '2000-01-01',
            'reference_type' => 'Opening',
            'description' => 'Test:unbalanced',
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('journal_entry_lines')->insert([
            'journal_entry_id' => $entryId,
            'account_id' => $cash->id,
            'debit' => 100,
            'credit' => 0,
            'memo' => null,
        ]);
    }

    public function test_sale_route_blocked_until_opening_locked(): void
    {
        $owner = $this->owner();

        // Not locked yet → guided into the step-by-step setup wizard.
        $this->actingAs($owner)->get('/sales/create')
            ->assertRedirect(route('opening.setup'));

        // Lock it, then the sale screen opens.
        app(PeriodLockService::class)->lockOpening($owner->id);

        $this->actingAs($owner)->get('/sales/create')->assertOk();
    }

    public function test_owner_can_lock_opening_via_route(): void
    {
        $owner = $this->owner();

        $this->assertFalse(app(PeriodLockService::class)->isOpeningLocked());

        $this->actingAs($owner)->post('/opening/lock', ['start_date' => now()->toDateString()])
            ->assertRedirect(route('opening.index'));

        $this->assertTrue(app(PeriodLockService::class)->isOpeningLocked());
    }

    public function test_review_screen_renders_for_owner(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get('/opening')
            ->assertOk()
            ->assertSee(__('ui.opening.overall'))
            ->assertSee(__('ui.opening.checks'))
            ->assertSee(__('ui.opening.review_lock'));
    }

    public function test_lock_is_blocked_when_books_not_balanced(): void
    {
        $owner = $this->owner();
        $this->unbalanceTheBooks($owner->id);

        $this->actingAs($owner)->post('/opening/lock')
            ->assertRedirect(route('opening.index'))
            ->assertSessionHas('warning');

        $this->assertFalse(app(PeriodLockService::class)->isOpeningLocked());
    }

    public function test_audit_info_shows_after_lock(): void
    {
        $owner = $this->owner();
        app(PeriodLockService::class)->lockOpening($owner->id);

        $this->actingAs($owner)->get('/opening')
            ->assertOk()
            ->assertSee(__('ui.opening.audit'))
            ->assertSee(__('ui.opening.locked_by'))
            ->assertSee($owner->name)
            ->assertDontSee(__('ui.opening.review_lock'));
    }

    public function test_review_screen_is_owner_only(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)->get('/opening')->assertForbidden();
    }
}
