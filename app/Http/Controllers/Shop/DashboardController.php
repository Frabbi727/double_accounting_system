<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Account;
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

    public function index(Request $request)
    {
        $today = now()->toDateString();

        // Today's sales = credits to Sales Revenue dated today.
        $todaySales = $this->accountMovementOnDate('4010', $today, 'credit');

        $cash = $this->balance('1010');
        $receivable = $this->balance('1030');
        $payable = $this->balance('2010');
        $stockValue = $this->reports->stock()['total_value'];

        // Month profit — only for users allowed to see cost/profit.
        $monthProfit = null;
        if ($request->user()->can('cost.view')) {
            $monthProfit = $this->reports->profitAndLoss(
                asOf: $today,
                from: now()->startOfMonth()->toDateString(),
            )['net_profit'];
        }

        return view('shop.dashboard', compact(
            'todaySales', 'cash', 'receivable', 'payable', 'stockValue', 'monthProfit'
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
}
