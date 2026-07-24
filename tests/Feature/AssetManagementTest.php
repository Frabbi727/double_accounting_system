<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AssetCategorySeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Asset\Models\Asset;
use Modules\Asset\Models\AssetCategory;
use Modules\Asset\Services\AssetService;
use Tests\TestCase;

/**
 * Asset Management (requirement §3): capitalizes fixed assets with automatic,
 * balanced double-entry across the three payment modes, keeps documents/voucher/
 * audit, and disposes by reversing the entry. Tests run APP_LOCALE=bn, so we
 * assert on account codes/reference_type, not localized labels.
 */
class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AssetCategorySeeder::class);
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function balance(string $code): float
    {
        return $this->ledger()->balance(Account::where('code', $code)->first());
    }

    private function category(string $nameEn): AssetCategory
    {
        return AssetCategory::where('name_en', $nameEn)->firstOrFail();
    }

    private function owner(): User
    {
        $u = User::factory()->create();
        $u->assignRole('owner');

        return $u;
    }

    /** @param array<string,mixed> $overrides */
    private function makeAsset(array $overrides = []): Asset
    {
        $this->actingAs($this->owner());

        return app(AssetService::class)->create(array_merge([
            'asset_category_id' => $this->category('Furniture & Fixtures')->id,
            'name' => 'অফিস চেয়ার',
            'purchase_date' => '2026-08-06',
            'amount' => 5000,
            'payment_mode' => 'account',
            'payment_account_id' => Account::where('code', '1010')->value('id'),
        ], $overrides));
    }

    public function test_paid_from_account_posts_a_balanced_entry(): void
    {
        $cashBefore = $this->balance('1010');

        $asset = $this->makeAsset(['amount' => 5000]);

        // Debit Furniture 1510, credit Cash 1010.
        $this->assertSame(5000.0, $this->balance('1510'));
        $this->assertSame($cashBefore - 5000.0, $this->balance('1010'));

        $this->assertMatchesRegularExpression('/^AST\d{5}$/', $asset->asset_no);
        $this->assertNotNull($asset->journal_entry_id);
        $this->assertSame('AssetPurchase', $asset->journalEntry->reference_type);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_category_maps_to_its_own_account(): void
    {
        $this->makeAsset([
            'asset_category_id' => $this->category('Vehicle')->id,
            'amount' => 300000,
        ]);

        $this->assertSame(300000.0, $this->balance('1540'));   // Vehicles
        $this->assertSame(0.0, $this->balance('1510'));         // Furniture untouched
    }

    public function test_on_credit_raises_payable_and_supplier_due(): void
    {
        $supplier = Supplier::create(['name' => 'ফার্নিচার হাউস']);
        $payableBefore = $this->balance('2010');

        $this->makeAsset([
            'amount' => 12000,
            'payment_mode' => 'credit',
            'payment_account_id' => null,
            'supplier_id' => $supplier->id,
        ]);

        $this->assertSame(12000.0, $this->balance('1510'));
        $this->assertSame($payableBefore + 12000.0, $this->balance('2010'));

        // Unpaid asset shows in the supplier's due (ReportService extension).
        $this->assertSame(12000.0, app(ReportService::class)->partyDue('supplier', $supplier->id));
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_opening_asset_credits_owner_equity(): void
    {
        $equityBefore = $this->balance('3010');

        $asset = $this->makeAsset([
            'name' => 'পুরনো আলমারি',
            'purchase_date' => '2026-07-01',    // inside the (unlocked) opening period
            'amount' => 8000,
            'payment_mode' => 'opening',
            'payment_account_id' => null,
        ]);

        $this->assertSame(8000.0, $this->balance('1510'));
        $this->assertSame($equityBefore + 8000.0, $this->balance('3010'));
        $this->assertSame('Opening', $asset->journalEntry->reference_type);
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_documents_are_stored(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner());

        $asset = app(AssetService::class)->create([
            'asset_category_id' => $this->category('Computer / Laptop')->id,
            'name' => 'ল্যাপটপ',
            'purchase_date' => '2026-08-06',
            'amount' => 60000,
            'payment_mode' => 'account',
            'payment_account_id' => Account::where('code', '1010')->value('id'),
        ], [UploadedFile::fake()->image('invoice.jpg')]);

        $this->assertCount(1, $asset->documents);
        Storage::disk('public')->assertExists($asset->documents->first()->path);
    }

    public function test_dispose_reverses_the_entry(): void
    {
        $asset = $this->makeAsset(['amount' => 5000]);
        $this->assertSame(5000.0, $this->balance('1510'));

        app(AssetService::class)->dispose($asset->fresh('journalEntry'), 'বিক্রি করা হয়েছে');

        $this->assertSame(0.0, $this->balance('1510'));
        $this->assertTrue($asset->fresh()->disposed());
        $this->ledger()->assertLedgerBalanced();
    }

    public function test_dispose_is_owner_only(): void
    {
        $asset = $this->makeAsset();

        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant)
            ->post(route('assets.dispose', $asset), ['dispose_reason' => 'x'])
            ->assertForbidden();

        $this->assertFalse($asset->fresh()->disposed());
    }

    public function test_salesperson_cannot_access_assets(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get(route('assets.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('assets.create'))->assertForbidden();
    }

    public function test_screens_render_for_owner(): void
    {
        $asset = $this->makeAsset();
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('assets.index'))->assertOk()->assertSee($asset->asset_no);
        $this->actingAs($owner)->get(route('assets.create'))->assertOk();
        $this->actingAs($owner)->get(route('assets.show', $asset))->assertOk()->assertSee($asset->name);
        $this->actingAs($owner)->get(route('asset-categories.index'))->assertOk();
    }

    public function test_store_via_http_creates_asset_and_entry(): void
    {
        $this->actingAs($this->owner())
            ->post(route('assets.store'), [
                'asset_category_id' => $this->category('Office Equipment')->id,
                'name' => 'প্রিন্টার',
                'purchase_date' => '2026-08-06',
                'amount' => 15000,
                'payment_mode' => 'account',
                'payment_account_id' => Account::where('code', '1010')->value('id'),
            ])
            ->assertRedirect();

        $this->assertSame(15000.0, $this->balance('1520'));   // Office Equipment
        $this->assertDatabaseHas('assets', ['name' => 'প্রিন্টার', 'payment_mode' => 'account']);
    }
}
