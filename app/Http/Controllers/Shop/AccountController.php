<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Master\AccountService;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accounts,
        private LedgerService $ledger,
    ) {}

    public function index()
    {
        $accounts = Account::whereIn('subtype', ['cash', 'bank', 'loan'])
            ->orderBy('code')->get();

        return view('shop.account.index', [
            'accounts' => $accounts->map(fn (Account $a) => [
                'model'   => $a,
                'balance' => $this->ledger->balance($a),
            ]),
        ]);
    }

    public function create()
    {
        return view('shop.account.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'subtype'         => ['required', 'in:cash,bank,loan'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Cash/bank are assets; a loan is a liability.
        $data['type'] = $data['subtype'] === 'loan' ? 'liability' : 'asset';

        $this->accounts->create($data);

        return redirect()->route('accounts.index')->with('status', __('ui.common.saved'));
    }
}
