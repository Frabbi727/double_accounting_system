<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Master\AccountService;
use Modules\Accounting\Services\Reporting\ReportService;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accounts,
        private LedgerService $ledger,
        private ReportService $reports,
    ) {}

    public function index(): View
    {
        $accounts = Account::whereIn('subtype', ['cash', 'bank', 'loan'])
            ->orderBy('code')->get();

        return view('shop.account.index', [
            'accounts' => $accounts->map(fn (Account $a) => [
                'model' => $a,
                'balance' => $this->ledger->balance($a),
            ]),
        ]);
    }

    /**
     * Full activity statement for one account — how money came in, where it
     * went, to whom, and the running balance — so account history is auditable.
     */
    public function statement(Request $request, Account $account): View
    {
        $from = (string) $request->input('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        return view('shop.account.statement', [
            'from'   => $from,
            'to'     => $to,
            'report' => $this->reports->accountStatement($account, $from, $to),
        ]);
    }

    public function create(): View
    {
        return view('shop.account.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subtype' => ['required', 'in:cash,bank,loan'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Cash/bank are assets; a loan is a liability.
        $data['type'] = $data['subtype'] === 'loan' ? 'liability' : 'asset';

        $this->accounts->create($data);

        return redirect()->route('accounts.index')->with('status', __('ui.common.saved'));
    }
}
