<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Services\PurchaseService;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchases,
    ) {}

    public function index()
    {
        return view('shop.purchase.index', [
            'purchases' => Purchase::latest('date')->latest('id')->limit(50)->get(),
        ]);
    }

    public function create()
    {
        return view('shop.purchase.create', [
            'products'  => Product::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id'        => ['nullable', 'exists:suppliers,id'],
            'date'               => ['required', 'date'],
            'landed_cost'        => ['nullable', 'numeric', 'min:0'],
            'paid_amount'        => ['nullable', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty'        => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost'  => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $this->purchases->create($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('purchases.index')->with('status', __('ui.common.saved'));
    }
}
