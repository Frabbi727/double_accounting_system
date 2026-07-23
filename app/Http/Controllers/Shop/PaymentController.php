<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Supplier;
use Modules\Finance\Services\PaymentService;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
    ) {}

    public function create()
    {
        return view('shop.payment.create', [
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'paymentAccounts' => Account::cashOrBank()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:received,made'],
            'party_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['nullable', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'amount' => $data['amount'],
            'date' => $data['date'],
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        try {
            if ($data['direction'] === 'received') {
                $customer = Customer::findOrFail($data['party_id']);
                $this->payments->receiveFromCustomer($customer, $payload);
            } else {
                $supplier = Supplier::findOrFail($data['party_id']);
                $this->payments->payToSupplier($supplier, $payload);
            }
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('payments.create')->with('status', __('ui.common.saved'));
    }
}
