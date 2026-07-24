<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\OpeningSummaryService;
use Modules\Accounting\Services\Accounting\PeriodLockService;

class OpeningController extends Controller
{
    public function __construct(
        private PeriodLockService $periodLock,
        private OpeningSummaryService $summary,
    ) {}

    public function index(): \Illuminate\View\View
    {
        $period = $this->periodLock->openingPeriod();
        $locked = $period->is_locked;

        return view('shop.opening.index', [
            'summary' => $this->summary->build(),
            'locked' => $locked,
            'lockedAt' => $locked ? $period->locked_at : null,
            'lockedBy' => $locked && $period->locked_by ? optional(User::find($period->locked_by))->name : null,
            'openingEntries' => $locked
                ? JournalEntry::opening()->with('lines.account')->orderBy('id')->get()
                : collect(),
        ]);
    }

    public function lock(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // Already locked, or a hard blocker (books not balanced) — never lock.
        if ($this->periodLock->isOpeningLocked()) {
            return redirect()->route('opening.index');
        }

        if ($this->summary->build()['has_blocker']) {
            return redirect()->route('opening.index')->with('warning', __('ui.opening.cannot_lock'));
        }

        $this->periodLock->lockOpening($user->id);

        return redirect()->route('opening.index')->with('status', __('ui.opening.locked_note'));
    }
}
