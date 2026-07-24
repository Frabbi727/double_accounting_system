<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\ProductCategory;
use Modules\Accounting\Services\Master\ProductService;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProductCategorySeeder::class);
        $this->seed(UnitSeeder::class);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->assignRole('owner');

        return $user;
    }

    public function test_owner_creates_product_with_subcategory_and_custom_unit(): void
    {
        $sub = ProductCategory::whereNotNull('parent_id')->first();

        $this->actingAs($this->owner())->post('/products', [
            'name' => 'Test Soap',
            'product_category_id' => $sub->id,
            'unit' => 'কার্টন', // free-typed unit, not in the seeded list
            'cost_price' => 40,
            'sale_price' => 55,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Test Soap',
            'product_category_id' => $sub->id,
            'unit' => 'কার্টন',
        ]);
    }

    public function test_sku_is_auto_generated_and_unique(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/products', [
            'name' => 'Alpha', 'unit' => 'pcs', 'cost_price' => 5, 'sale_price' => 8,
        ])->assertRedirect();
        $this->actingAs($owner)->post('/products', [
            'name' => 'Beta', 'unit' => 'pcs', 'cost_price' => 5, 'sale_price' => 8,
        ])->assertRedirect();

        $skus = Product::pluck('sku');

        // Every product has a system SKU, all non-null and distinct.
        $this->assertTrue($skus->every(fn ($s) => ! empty($s)));
        $this->assertSame($skus->count(), $skus->unique()->count());
        $this->assertMatchesRegularExpression('/^P\d{5}$/', Product::where('name', 'Alpha')->value('sku'));
    }

    public function test_owner_edits_product(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'Old', 'unit' => 'pcs', 'cost_price' => 10, 'sale_price' => 12,
        ]);

        $this->actingAs($this->owner())->put("/products/{$product->id}", [
            'name' => 'New Name',
            'unit' => 'kg',
            'sale_price' => 20,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name', 'unit' => 'kg', 'sale_price' => 20]);
    }

    public function test_unused_product_is_hard_deleted(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'Ghost', 'unit' => 'pcs', 'cost_price' => 10, 'sale_price' => 12,
        ]);

        $this->actingAs($this->owner())->delete("/products/{$product->id}")
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_with_history_is_deactivated_not_deleted(): void
    {
        // Opening stock creates a stock_movements row → history exists.
        $product = app(ProductService::class)->create([
            'name' => 'Has Stock', 'unit' => 'pcs', 'cost_price' => 10, 'sale_price' => 12,
            'opening_qty' => 5, 'opening_cost' => 10, 'opening_date' => config('shop.cutoff_date'),
        ]);

        $this->actingAs($this->owner())->delete("/products/{$product->id}")
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_show_page_renders_history(): void
    {
        $product = app(ProductService::class)->create([
            'name' => 'Detailed', 'unit' => 'pcs', 'cost_price' => 10, 'sale_price' => 12,
            'opening_qty' => 3, 'opening_cost' => 10, 'opening_date' => config('shop.cutoff_date'),
        ]);

        $this->actingAs($this->owner())->get("/products/{$product->id}")
            ->assertOk()
            ->assertSee('Detailed');
    }

    public function test_inline_category_add_returns_json(): void
    {
        $parent = ProductCategory::whereNull('parent_id')->first();

        $this->actingAs($this->owner())->postJson('/product-categories', [
            'name_bn' => 'নতুন সাব', 'name_en' => 'New Sub', 'parent_id' => $parent->id, 'inline' => true,
        ])->assertOk()->assertJsonStructure(['id', 'name', 'parent_id']);

        $this->assertDatabaseHas('product_categories', ['name_en' => 'New Sub', 'parent_id' => $parent->id]);
    }

    public function test_create_edit_and_category_pages_render(): void
    {
        $owner = $this->owner();
        $product = app(ProductService::class)->create([
            'name' => 'Render', 'unit' => 'pcs', 'cost_price' => 10, 'sale_price' => 12,
        ]);

        // Unit select carries the "other / free-type" option; category picker is Alpine-driven.
        $this->actingAs($owner)->get('/products/create')->assertOk()->assertSee('__other__', false);
        $this->actingAs($owner)->get("/products/{$product->id}/edit")->assertOk()->assertSee('Render');
        $this->actingAs($owner)->get('/product-categories')->assertOk();
    }

    public function test_salesperson_cannot_manage_products(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('salesperson');

        $this->actingAs($sales)->get('/products/create')->assertForbidden();
        $this->actingAs($sales)->post('/products', ['name' => 'X', 'unit' => 'pcs', 'cost_price' => 1, 'sale_price' => 1])
            ->assertForbidden();
    }
}
