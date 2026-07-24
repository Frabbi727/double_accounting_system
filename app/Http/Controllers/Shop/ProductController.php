<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreProductRequest;
use Modules\Accounting\Http\Requests\UpdateProductRequest;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\ProductCategory;
use Modules\Accounting\Models\Unit;
use Modules\Accounting\Services\Master\ProductService;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $products,
    ) {}

    public function index(): \Illuminate\View\View
    {
        return view('shop.product.index', [
            'products' => Product::with('category.parent')->orderBy('name')->get(),
        ]);
    }

    public function create(): \Illuminate\View\View
    {
        return view('shop.product.create', $this->formData());
    }

    public function store(StoreProductRequest $request): \Illuminate\Http\RedirectResponse
    {
        $this->products->create($request->validated());

        return redirect()->route('products.index')->with('status', __('ui.common.saved'));
    }

    public function show(Product $product): \Illuminate\View\View
    {
        return view('shop.product.show', [
            'product' => $product->load('category.parent'),
            'movements' => $product->movements()
                ->orderBy('date', 'desc')->orderBy('id', 'desc')->get(),
        ]);
    }

    public function edit(Product $product): \Illuminate\View\View
    {
        return view('shop.product.edit', array_merge($this->formData(), [
            'product' => $product,
        ]));
    }

    public function update(UpdateProductRequest $request, Product $product): \Illuminate\Http\RedirectResponse
    {
        $this->products->update($product, $request->validated());

        return redirect()->route('products.index')->with('status', __('ui.common.saved'));
    }

    /**
     * Smart delete: a product with any stock history cannot be hard-deleted
     * without breaking the ledger/reports, so it is deactivated instead
     * (drops out of every transaction picker, history stays intact).
     */
    public function destroy(Product $product): \Illuminate\Http\RedirectResponse
    {
        if ($product->movements()->exists()) {
            $product->update(['is_active' => false]);

            return redirect()->route('products.index')
                ->with('status', __('ui.product.deactivated'));
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('status', __('ui.product.deleted'));
    }

    /** 
     * Shared category tree + unit list for the create/edit forms.
     * 
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => ProductCategory::whereNull('parent_id')
                ->with('children')->orderBy('name_bn')->get(),
            'units' => Unit::where('is_active', true)->orderBy('name_bn')->get(),
        ];
    }
}
