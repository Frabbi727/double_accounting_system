<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Tests\TestCase;

/**
 * A shopkeeper must never hit a dead-end (or a 500) when entering opening data.
 * Once business has started (opening locked), master forms hide the opening
 * field and submissions are guided back to setup — never a raw crash. The owner
 * can flip back to setup mode from the UI.
 */
class OpeningSetupModeTest extends TestCase
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

    private function lock(User $owner): void
    {
        app(PeriodLockService::class)->lockOpening($owner->id);
    }

    public function test_owner_can_return_to_setup_mode(): void
    {
        $owner = $this->owner();
        $this->lock($owner);
        $this->assertTrue(app(PeriodLockService::class)->isOpeningLocked());

        $this->actingAs($owner)->post('/opening/unlock', ['reason' => 'বাকি যোগ করব'])
            ->assertRedirect(route('opening.index'))
            ->assertSessionHas('status');

        $this->assertFalse(app(PeriodLockService::class)->isOpeningLocked());
    }

    public function test_unlock_is_owner_only(): void
    {
        $owner = $this->owner();
        $this->lock($owner);

        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)->post('/opening/unlock')->assertForbidden();
    }

    public function test_supplier_opening_while_locked_is_guided_not_500(): void
    {
        $owner = $this->owner();
        $this->lock($owner);

        $this->actingAs($owner)->post('/suppliers', [
            'name' => 'ABC ট্রেডার্স',
            'opening_amount' => 5000,
        ])->assertRedirect()->assertSessionHas('warning');

        // The supplier was NOT created with a locked-date opening entry.
        $this->assertDatabaseMissing('journal_entries', ['reference_type' => 'Opening']);
    }

    public function test_supplier_create_form_hides_opening_when_locked(): void
    {
        $owner = $this->owner();

        // Setup mode: opening field visible.
        $this->actingAs($owner)->get('/suppliers/create')
            ->assertOk()
            ->assertSee(__('ui.supplier.opening_due'))
            ->assertDontSee(__('ui.opening.master_locked_note'));

        // Business started: field hidden, guidance shown.
        $this->lock($owner);
        $this->actingAs($owner)->get('/suppliers/create')
            ->assertOk()
            ->assertSee(__('ui.opening.master_locked_note'))
            ->assertSee(__('ui.opening.back_to_setup'));
    }

    public function test_supplier_without_opening_still_saves_while_locked(): void
    {
        $owner = $this->owner();
        $this->lock($owner);

        $this->actingAs($owner)->post('/suppliers', ['name' => 'নগদ সাপ্লায়ার'])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('suppliers', ['name' => 'নগদ সাপ্লায়ার']);
    }
}
