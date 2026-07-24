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

        // The owner tells us, in plain language, which day daily bookkeeping
        // starts. Everything before it is opening; the cut-off is the day
        // before, so entries on/after the start day are always postable.
        $data = $request->validate([
            'start_date' => ['required', 'date'],
        ]);
        $cutoff = \Illuminate\Support\Carbon::parse($data['start_date'])->subDay()->toDateString();

        $this->periodLock->realignCutoff($cutoff);
        $this->periodLock->lockOpening($user->id);

        return redirect()->route('opening.index')->with('status', __('ui.opening.locked_note'));
    }

    /**
     * Owner recovery: change the business start date after opening is locked
     * (e.g. a shop that locked itself out of "today"). Audited unlock → realign
     * → re-lock, all in one guarded action so no console access is needed.
     */
    public function reopen(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $cutoff = \Illuminate\Support\Carbon::parse($data['start_date'])->subDay()->toDateString();

        $this->periodLock->reopenAndSetCutoff(
            $user->id,
            $cutoff,
            $data['reason'] ?? __('ui.opening.reopen_default_reason'),
        );

        return redirect()->route('opening.index')->with('status', __('ui.opening.start_date_changed'));
    }

    /**
     * Go back to setup mode: unlock the opening period so the owner can add or
     * fix opening balances (supplier/customer/product/account). Daily billing
     * pauses until they lock again. Audited via unlock_reason.
     */
    public function unlock(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (! $this->periodLock->isOpeningLocked()) {
            return redirect()->route('opening.index');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->periodLock->unlockOpening($user->id, $data['reason'] ?? __('ui.opening.unlock_default_reason'));

        return redirect()->route('opening.index')->with('status', __('ui.opening.unlocked_for_edit'));
    }
}
