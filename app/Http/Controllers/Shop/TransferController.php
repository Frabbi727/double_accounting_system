<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Finance\Services\TransferService;

class TransferController extends Controller
{
    public function __construct(
        private TransferService $transfers,
    ) {}

    public function create()
    {
        return view('shop.transfer.create', [
            'accounts' => Account::whereIn('subtype', ['cash', 'bank', 'loan'])->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'exists:accounts,id'],
            'to_account_id' => ['required', 'exists:accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->transfers->transfer(
                Account::findOrFail($data['from_account_id']),
                Account::findOrFail($data['to_account_id']),
                ['amount' => $data['amount'], 'date' => $data['date'], 'notes' => $data['notes'] ?? null],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('transfers.create')->with('status', __('ui.common.saved'));
    }
}
