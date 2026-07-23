<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Services\Accounting\PeriodLockService;
use Modules\Accounting\Services\Reporting\ReportService;

class OpeningController extends Controller
{
    public function __construct(
        private PeriodLockService $periodLock,
        private ReportService $reports,
    ) {}

    public function index()
    {
        $bs = $this->reports->balanceSheet();

        return view('shop.opening.index', [
            'totalAssets'      => $bs['total_assets'],
            'totalLiabilities' => $bs['total_liabilities'],
            'totalEquity'      => $bs['total_equity'],
            'balanced'         => $bs['balanced'],
            'locked'           => $this->periodLock->isOpeningLocked(),
        ]);
    }

    public function lock(Request $request)
    {
        $this->periodLock->lockOpening($request->user()->id);

        return redirect()->route('opening.index')->with('status', __('ui.opening.locked_note'));
    }
}
