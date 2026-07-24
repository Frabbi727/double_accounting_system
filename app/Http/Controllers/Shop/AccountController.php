<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Master\AccountService;
use Modules\Accounting\Services\Reporting\ReportService;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accounts,
        private LedgerService $ledger,
        private ReportService $reports,
        private PeriodLockService $periodLock,
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
            'openingLocked' => $this->periodLock->isOpeningLocked(),
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

    /**
     * Set or edit the opening balance of an existing account. Pre-lock only.
     * The current opening equals the live ledger balance (nothing else is posted
     * before the opening period is locked).
     */
    public function editOpening(Account $account): View|RedirectResponse
    {
        if ($this->periodLock->isOpeningLocked()) {
            return redirect()->route('accounts.index')->with('warning', __('ui.account.opening_locked_note'));
        }

        return view('shop.account.opening', [
            'account' => $account,
            'current' => $this->ledger->balance($account),
        ]);
    }

    public function updateOpening(Request $request, Account $account): RedirectResponse
    {
        if ($this->periodLock->isOpeningLocked()) {
            return redirect()->route('accounts.index')->with('warning', __('ui.account.opening_locked_note'));
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->accounts->setOpening(
            $account,
            (float) $data['amount'],
            $data['reason'] ?? __('ui.account.opening_default_reason'),
        );

        return redirect()->route('accounts.index')->with('status', __('ui.account.opening_saved'));
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
