<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return view('shop.category.index', [
            'categories' => ProductCategory::whereNull('parent_id')
                ->with('children')->orderBy('name_bn')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name_bn' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
        ]);

        // A sub-category cannot itself be nested under another sub-category.
        if (! empty($data['parent_id'])
            && ProductCategory::whereKey($data['parent_id'])->whereNotNull('parent_id')->exists()) {
            return back()->withErrors(['parent_id' => __('ui.category.nested_error')])->withInput();
        }

        $category = ProductCategory::create($data);

        // Inline add from the product form (AJAX) → return the new row so the
        // form can append + pre-select it without losing the entered data.
        if ($request->boolean('inline')) {
            return response()->json([
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
            ]);
        }

        return redirect()->route('product-categories.index')
            ->with('status', __('ui.common.saved'));
    }

    public function destroy(ProductCategory $product_category)
    {
        if ($product_category->children()->exists() || $product_category->products()->exists()) {
            return back()->with('warning', __('ui.category.in_use'));
        }

        $product_category->delete();

        return redirect()->route('product-categories.index')
            ->with('status', __('ui.product.deleted'));
    }
}
