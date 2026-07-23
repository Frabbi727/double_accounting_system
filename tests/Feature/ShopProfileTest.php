<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ShopProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopProfileTest extends TestCase
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

    public function test_profile_and_logo_are_saved(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post('/shop-profile', [
            'name' => 'রাব্বি স্টোর',
            'address' => 'ঢাকা',
            'phone' => '01700000000',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect(route('shop-profile.edit'));

        $this->assertSame('রাব্বি স্টোর', ShopProfile::name());
        $this->assertSame('ঢাকা', ShopProfile::address());

        $path = Setting::get('shop.logo');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_removing_logo_deletes_the_file(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('old.png')->store('logo', 'public');
        Setting::put('shop.logo', $path);

        $this->actingAs($this->owner)->post('/shop-profile', [
            'name' => 'রাব্বি স্টোর',
            'remove_logo' => 1,
        ])->assertRedirect(route('shop-profile.edit'));

        $this->assertNull(Setting::get('shop.logo'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_name_appears_on_invoice_when_no_profile_uses_config_fallback(): void
    {
        // With nothing set, the name falls back to config.
        $this->assertSame(config('shop.name'), ShopProfile::name());
    }

    public function test_salesperson_cannot_edit_profile(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get('/shop-profile')->assertForbidden();
        $this->actingAs($sales)->post('/shop-profile', ['name' => 'x'])->assertForbidden();
    }
}
