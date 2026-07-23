<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $sales,
    ) {}

    public function index()
    {
        return view('shop.sale.index', [
            'sales' => Sale::latest('date')->latest('id')->limit(50)->get(),
        ]);
    }

    public function create()
    {
        return view('shop.sale.create', [
            'products'  => Product::where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'        => ['nullable', 'exists:customers,id'],
            'date'               => ['required', 'date'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'paid_amount'        => ['nullable', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty'        => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount'   => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->sales->create($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            // Service-level guards (no stock, unbalanced, etc.) → readable message.
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('sales.index')->with('status', __('ui.common.saved'));
    }
}
