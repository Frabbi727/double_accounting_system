<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\OpeningEntryService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\ProductService;
use Tests\TestCase;

class ReportExportTest extends TestCase
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
        $this->actingAs($this->owner);

        $cash = Account::where('code', '1010')->first();
        app(OpeningEntryService::class)->post(
            account: $cash, amount: 5000,
            date: config('shop.cutoff_date'), source: $cash,
        );
        app(ProductService::class)->create([
            'name' => 'সাবান', 'unit' => 'pcs',
            'cost_price' => 40, 'sale_price' => 55,
            'opening_qty' => 10, 'opening_cost' => 40,
        ]);
        app(PeriodLockService::class)->lockOpening($this->owner->id);
    }

    public function test_trial_balance_csv_export(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('reports.export.trial_balance', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('1010', $response->streamedContent());
    }

    public function test_stock_pdf_export(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('reports.export.stock', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        // A real PDF starts with the %PDF- magic bytes.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_default_format_is_csv(): void
    {
        $this->actingAs($this->owner)->get(route('reports.export.stock'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_salesperson_cannot_export(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get(route('reports.export.trial_balance'))->assertForbidden();
        $this->actingAs($sales)->get(route('reports.export.stock', ['format' => 'pdf']))->assertForbidden();
    }
}
