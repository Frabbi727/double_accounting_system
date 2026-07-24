<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Reporting\ReportService;

class DashboardController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private PeriodLockService $periodLock,
        private ReportService $reports,
    ) {}

    public function index(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // First-run: opening balances not locked yet → guide the owner into the
        // step-by-step setup wizard instead of showing an empty dashboard.
        if (! $this->periodLock->isOpeningLocked() && $user->can('opening.manage')) {
            return redirect()->route('opening.setup');
        }

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // Today's sales / this month's sales = credits to Sales Revenue.
        $todaySales = $this->accountMovementOnDate('4010', $today, 'credit');
        $monthSales = $this->accountMovementBetween('4010', $monthStart, $today, 'credit');

        $cash = $this->balance('1010');
        $receivable = $this->balance('1030');
        $payable = $this->balance('2010');

        $stock = $this->reports->stock();
        $stockValue = $stock['total_value'];
        $lowStock = collect($stock['rows'])->filter(fn ($r) => $r['low_stock'])->values();

        // Month profit — only for users allowed to see cost/profit.
        $monthProfit = null;
        if ($user->can('cost.view')) {
            $monthProfit = $this->reports->profitAndLoss(
                asOf: $today,
                from: $monthStart,
            )['net_profit'];
        }

        // Recent ledger activity — only for report viewers.
        $recent = $user->can('report.view')
            ? JournalEntry::latest('date')->latest('id')->limit(6)->get()
            : collect();

        return view('shop.dashboard', compact(
            'todaySales', 'monthSales', 'cash', 'receivable', 'payable',
            'stockValue', 'monthProfit', 'lowStock', 'recent'
        ) + ['openingLocked' => $this->periodLock->isOpeningLocked()]);
    }

    private function balance(string $code): float
    {
        $account = Account::where('code', $code)->first();

        return $account ? $this->ledger->balance($account) : 0.0;
    }

    private function accountMovementOnDate(string $code, string $date, string $side): float
    {
        $account = Account::where('code', $code)->first();
        if (! $account) {
            return 0.0;
        }

        return (float) $account->lines()
            ->whereHas('journalEntry', fn ($q) => $q->whereDate('date', $date))
            ->sum($side);
    }

    private function accountMovementBetween(string $code, string $from, string $to, string $side): float
    {
        $account = Account::where('code', $code)->first();
        if (! $account) {
            return 0.0;
        }

        return (float) $account->lines()
            ->whereHas('journalEntry', fn ($q) => $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to))
            ->sum($side);
    }
}
