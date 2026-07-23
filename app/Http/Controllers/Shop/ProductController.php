<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreProductRequest;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Services\Master\ProductService;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $products,
    ) {}

    public function index()
    {
        return view('shop.product.index', [
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('shop.product.create');
    }

    public function store(StoreProductRequest $request)
    {
        $this->products->create($request->validated());

        return redirect()->route('products.index')->with('status', __('ui.common.saved'));
    }
}
