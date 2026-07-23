<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
    }

    public function test_owner_can_create_a_user_with_a_role(): void
    {
        $this->actingAs($this->owner)->post('/users', [
            'name' => 'নতুন হিসাবরক্ষক',
            'email' => 'acc@shop.test',
            'password' => 'password',
            'role' => 'accountant',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'acc@shop.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('accountant'));
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_owner_can_change_role_and_keep_password_when_blank(): void
    {
        $staff = User::factory()->create(['password' => Hash::make('original')]);
        $staff->assignRole('salesperson');

        $this->actingAs($this->owner)->patch("/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => '',
            'role' => 'accountant',
        ])->assertRedirect(route('users.index'));

        $staff->refresh();
        $this->assertTrue($staff->hasRole('accountant'));
        $this->assertFalse($staff->hasRole('salesperson'));
        $this->assertTrue(Hash::check('original', $staff->password), 'blank password should not change it');
    }

    public function test_cannot_delete_self_or_last_owner(): void
    {
        // Deleting own account is refused.
        $this->actingAs($this->owner)->delete("/users/{$this->owner->id}")
            ->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $this->owner->id]);

        // Demoting the only owner is refused.
        $this->actingAs($this->owner)->patch("/users/{$this->owner->id}", [
            'name' => $this->owner->name,
            'email' => $this->owner->email,
            'role' => 'accountant',
        ])->assertSessionHasErrors('role');
        $this->assertTrue($this->owner->fresh()->hasRole('owner'));
    }

    public function test_non_owner_cannot_manage_users(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)->get('/users')->assertForbidden();
        $this->actingAs($accountant)->post('/users', [
            'name' => 'x', 'email' => 'x@shop.test', 'password' => 'password', 'role' => 'owner',
        ])->assertForbidden();
    }
}
