<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('owner');
    }

    public function test_owner_can_download_a_json_backup(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backup.download'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $data = json_decode($body, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('tables', $data);
        $this->assertArrayHasKey('accounts', $data['tables']);
        // The seeded chart of accounts is present in the snapshot.
        $this->assertStringContainsString('1010', $body);
    }

    public function test_backup_page_renders_for_owner(): void
    {
        $this->actingAs($this->owner)->get(route('backup.index'))
            ->assertOk()
            ->assertSee(__('ui.backup.download'));
    }

    public function test_non_owner_cannot_backup(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)->get(route('backup.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('backup.download'))->assertForbidden();
    }
}
