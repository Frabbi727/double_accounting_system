<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Finance\Services\TransferService;

class TransferController extends Controller
{
    public function __construct(
        private TransferService $transfers,
        private LedgerService $ledger,
    ) {}

    public function create()
    {
        $accounts = Account::whereIn('subtype', ['cash', 'bank', 'loan'])->orderBy('code')->get();

        return view('shop.transfer.create', [
            'accounts' => $accounts,
            // Only cash/bank sources have a spendable cap; loan accounts → null (no block).
            'accountBalances' => $accounts->mapWithKeys(fn ($a) => [
                $a->id => in_array($a->subtype, ['cash', 'bank'], true) ? $this->ledger->balance($a) : null,
            ]),
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
