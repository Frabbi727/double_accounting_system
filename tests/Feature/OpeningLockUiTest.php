<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_sale_route_blocked_until_opening_locked(): void
    {
        $owner = $this->owner();

        // Not locked yet → redirected to the opening screen.
        $this->actingAs($owner)->get('/sales/create')
            ->assertRedirect(route('opening.index'));

        // Lock it, then the sale screen opens.
        app(PeriodLockService::class)->lockOpening($owner->id);

        $this->actingAs($owner)->get('/sales/create')->assertOk();
    }

    public function test_owner_can_lock_opening_via_route(): void
    {
        $owner = $this->owner();

        $this->assertFalse(app(PeriodLockService::class)->isOpeningLocked());

        $this->actingAs($owner)->post('/opening/lock')->assertRedirect(route('opening.index'));

        $this->assertTrue(app(PeriodLockService::class)->isOpeningLocked());
    }
}
