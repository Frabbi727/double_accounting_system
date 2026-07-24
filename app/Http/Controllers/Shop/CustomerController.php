<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Accounting\Http\Requests\StoreCustomerRequest;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Services\Master\CustomerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Incentive\Models\PartyIncentive;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customers,
        private ReportService $reports,
    ) {}

    public function index(): \Illuminate\View\View
    {
        $customers = Customer::orderBy('name')->get();

        return view('shop.customer.index', [
            'customers' => $customers,
            // Live ledger due per customer (0 once settled) — never the frozen opening.
            'dues' => $customers->mapWithKeys(
                fn (Customer $c) => [$c->id => $this->reports->partyDue('customer', $c->id)]
            ),
        ]);
    }

    /** Full history/statement for one customer — reachable even at zero due. */
    public function show(Customer $customer): \Illuminate\View\View
    {
        return view('shop.customer.show', [
            'record' => $customer,
            'statement' => $this->reports->partyStatement('customer', $customer->id),
            'incentives' => PartyIncentive::where('party_type', 'customer')
                ->where('party_id', $customer->id)->latest('date')->latest('id')->get(),
        ]);
    }

    public function create(): \Illuminate\View\View
    {
        return view('shop.customer.create');
    }

    public function store(StoreCustomerRequest $request): \Illuminate\Http\RedirectResponse
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
