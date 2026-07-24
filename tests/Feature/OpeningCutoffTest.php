<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Accounting\Exceptions\PeriodLockedException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Tests\TestCase;

/**
 * The business-start-date (cut-off) flow: a shop must never be locked out of
 * "today", and the owner can realign the start date from the UI if it happens.
 */
class OpeningCutoffTest extends TestCase
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

    /** Post a trivially-balanced entry on $date (Dr Cash / Cr Owner's Equity). */
    private function postOn(string $date): void
    {
        app(LedgerService::class)->post(
            date: $date,
            referenceType: 'Test',
            referenceId: null,
            description: 'probe',
            lines: [
                ['account_id' => Account::code('1010')->firstOrFail()->id, 'debit' => 10, 'credit' => 0],
                ['account_id' => Account::code('3010')->firstOrFail()->id, 'debit' => 0, 'credit' => 10],
            ],
        );
    }

    public function test_locking_with_start_today_allows_today_and_rejects_earlier(): void
    {
        $owner = $this->owner();
        $today = Carbon::today()->toDateString();

        $this->actingAs($owner)->post('/opening/lock', ['start_date' => $today])
            ->assertRedirect(route('opening.index'));

        $this->assertTrue(app(PeriodLockService::class)->isOpeningLocked());

        // Cut-off = yesterday, so today posts fine...
        $this->postOn($today);

        // ...but a date before the start day is inside the locked window.
        $this->expectException(PeriodLockedException::class);
        $this->postOn(Carbon::today()->subDays(3)->toDateString());
    }

    public function test_owner_can_change_start_date_after_lock(): void
    {
        $owner = $this->owner();

        // Lock with the cut-off set to tomorrow — this traps "today".
        app(PeriodLockService::class)->realignCutoff(Carbon::today()->addDay()->toDateString());
        app(PeriodLockService::class)->lockOpening($owner->id);
        $this->assertTrue(app(PeriodLockService::class)->isLocked(Carbon::today()->toDateString()));

        // Owner fixes it from the UI: start = today.
        $this->actingAs($owner)->post('/opening/reopen', [
            'start_date' => Carbon::today()->toDateString(),
            'reason' => 'ভুল তারিখ ঠিক করা',
        ])->assertRedirect(route('opening.index'));

        // Still locked, but today is now postable and the reason is audited.
        $period = app(PeriodLockService::class)->openingPeriod();
        $this->assertTrue($period->is_locked);
        $this->assertFalse(app(PeriodLockService::class)->isLocked(Carbon::today()->toDateString()));
        $this->assertSame('ভুল তারিখ ঠিক করা', $period->unlock_reason);

        $this->postOn(Carbon::today()->toDateString()); // no exception
    }

    public function test_realign_is_idempotent(): void
    {
        $svc = app(PeriodLockService::class);
        $svc->realignCutoff('2026-07-10');
        $svc->realignCutoff('2026-07-10'); // no-op, no error

        $this->assertSame('2026-07-10', $svc->openingPeriod()->end_date->toDateString());
    }

    public function test_reopen_is_owner_only(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)
            ->post('/opening/reopen', ['start_date' => Carbon::today()->toDateString()])
            ->assertForbidden();
    }
}
