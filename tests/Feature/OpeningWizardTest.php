<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Asset\Models\AssetCategory;
use Tests\TestCase;

/**
 * The guided step-by-step opening-balance setup wizard: a friendly shell over
 * the existing master services. Verifies the journey, the auto-redirect into
 * it while unlocked, and that each step posts a balanced opening entry.
 */
class OpeningWizardTest extends TestCase
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

    public function test_unlocked_owner_is_redirected_from_dashboard_into_the_wizard(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertRedirect(route('opening.setup'));
    }

    public function test_welcome_and_each_step_render(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('opening.setup'))
            ->assertOk()
            ->assertSee(__('ui.opening.wizard.welcome_title'));

        foreach (['cash', 'suppliers', 'customers', 'products', 'assets', 'review'] as $step) {
            $this->actingAs($owner)->get(route('opening.setup.step', $step))->assertOk();
        }
    }

    public function test_cash_step_posts_a_balanced_opening_entry(): void
    {
        $owner = $this->owner();
        $cash = Account::code('1010')->firstOrFail();

        $this->actingAs($owner)->post(route('opening.setup.cash'), [
            'amounts' => [$cash->id => 5000],
        ])->assertRedirect(route('opening.setup.step', 'cash'));

        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'Opening']);
        $this->assertEqualsWithDelta(5000, app(\Modules\Accounting\Services\Accounting\LedgerService::class)->balance($cash->fresh()), 0.01);
    }

    public function test_cash_step_shows_and_accepts_loan_accounts(): void
    {
        $owner = $this->owner();

        // A shop that borrowed money: a loan account (liability).
        $loan = app(\Modules\Accounting\Services\Master\AccountService::class)->create([
            'name' => 'ব্যাংক লোন', 'subtype' => 'loan', 'type' => 'liability',
        ]);

        // The loan appears on the cash step, ready for an opening amount.
        $this->actingAs($owner)->get(route('opening.setup.step', 'cash'))
            ->assertOk()
            ->assertSee(__('ui.opening.wizard.loan_section_title'))
            ->assertSee('ব্যাংক লোন');

        // Entering its opening balance posts a balanced opening entry.
        $this->actingAs($owner)->post(route('opening.setup.cash'), [
            'amounts' => [$loan->id => 20000],
        ])->assertRedirect(route('opening.setup.step', 'cash'));

        // Liability grows on the credit side → balance() returns +20000.
        $this->assertEqualsWithDelta(
            20000,
            app(\Modules\Accounting\Services\Accounting\LedgerService::class)->balance($loan->fresh()),
            0.01,
        );

        // Books stay balanced (contra = equity).
        $this->assertTrue(app(\Modules\Accounting\Services\Accounting\OpeningSummaryService::class)->build()['totals']['balanced']);
    }

    public function test_supplier_and_customer_steps_create_masters_with_dues(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('opening.setup.suppliers'), [
            'name' => 'ABC ট্রেডার্স', 'amount' => 3000,
        ])->assertRedirect(route('opening.setup.step', 'suppliers'));

        $this->actingAs($owner)->post(route('opening.setup.customers'), [
            'name' => 'করিম স্টোর', 'amount' => 1500,
        ])->assertRedirect(route('opening.setup.step', 'customers'));

        $this->assertDatabaseHas('suppliers', ['name' => 'ABC ট্রেডার্স']);
        $this->assertDatabaseHas('customers', ['name' => 'করিম স্টোর']);
    }

    public function test_product_step_creates_product_with_opening_stock(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('opening.setup.products'), [
            'name' => 'সাবান', 'opening_qty' => 10, 'cost_price' => 40, 'sale_price' => 55,
        ])->assertRedirect(route('opening.setup.step', 'products'));

        $this->assertDatabaseHas('products', ['name' => 'সাবান']);
        $this->assertDatabaseHas('stock_movements', ['reference_type' => 'Opening']);
    }

    public function test_asset_step_records_owned_asset_against_equity(): void
    {
        $owner = $this->owner();
        $account = Account::whereIn('type', ['Asset', 'asset'])->firstOrFail();
        $category = AssetCategory::create([
            'name_bn' => 'আসবাব', 'name_en' => 'Furniture',
            'account_id' => $account->id, 'is_system' => false, 'is_active' => true, 'sort' => 1,
        ]);

        $this->actingAs($owner)->post(route('opening.setup.assets'), [
            'asset_category_id' => $category->id, 'name' => 'চেয়ার-টেবিল', 'amount' => 8000,
        ])->assertRedirect(route('opening.setup.step', 'assets'));

        $this->assertDatabaseHas('assets', ['name' => 'চেয়ার-টেবিল', 'payment_mode' => 'opening']);
    }

    public function test_locked_owner_is_sent_to_advanced_page(): void
    {
        $owner = $this->owner();
        app(PeriodLockService::class)->lockOpening($owner->id);

        $this->actingAs($owner)->get(route('opening.setup'))
            ->assertRedirect(route('opening.index'));
    }
}
