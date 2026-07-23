<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Master\SupplierService;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierService $suppliers,
    ) {}

    public function index()
    {
        return view('shop.supplier.index', [
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('shop.supplier.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:suppliers,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_amount' => ['nullable', 'numeric', 'gt:0'],
            'opening_date' => ['nullable', 'date', 'before_or_equal:'.config('shop.cutoff_date')],
        ]);

        if (! empty($data['opening_amount'])) {
            $data['opening_items'] = [[
                'amount' => $data['opening_amount'],
                'original_date' => $data['opening_date'] ?? config('shop.cutoff_date'),
            ]];
        }

        $this->suppliers->create($data);

        return redirect()->route('suppliers.index')->with('status', __('ui.common.saved'));
    }
}
