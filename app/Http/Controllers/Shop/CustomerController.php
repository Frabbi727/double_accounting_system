<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreCustomerRequest;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Services\Master\CustomerService;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customers,
    ) {}

    public function index()
    {
        return view('shop.customer.index', [
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('shop.customer.create');
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();

        // A single opening-due form field maps to one opening_items row.
        if (! empty($data['opening_amount'] ?? null)) {
            $data['opening_items'] = [[
                'amount' => $data['opening_amount'],
                'original_date' => $data['opening_date'] ?? config('shop.cutoff_date'),
            ]];
        }

        $this->customers->create($data);

        return redirect()->route('customers.index')->with('status', __('ui.common.saved'));
    }
}
