<?php

namespace Modules\Accounting\Services\Accounting;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\StockMovement;

class PeriodLockService
{
    /** Is any locked period covering this date? */
    public function isLocked(string $date): bool
    {
        return AccountingPeriod::where('is_locked', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Lock the opening period. After this, no entry dated on or before the
     * cut-off date can be posted, and daily transactions become available.
     */
    public function lockOpening(int $userId): AccountingPeriod
    {
        $period = $this->openingPeriod();

        $period->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $userId,
        ]);

        return $period;
    }

    /**
     * Temporarily unlock to correct a mistake. Always re-lock afterwards.
     * The reason is stored permanently for audit.
     */
    public function unlockOpening(int $userId, string $reason): AccountingPeriod
    {
        $period = $this->openingPeriod();

        $period->update([
            'is_locked' => false,
            'unlock_reason' => $reason,
            'locked_by' => $userId,
        ]);

        return $period;
    }

    public function isOpeningLocked(): bool
    {
        return $this->openingPeriod()->is_locked;
    }

    public function openingPeriod(): AccountingPeriod
    {
        return AccountingPeriod::firstOrCreate(
            ['name' => 'Opening'],
            [
                // Everything up to and including the cut-off date.
                'start_date' => '1970-01-01',
                'end_date' => config('shop.cutoff_date'),
                'is_locked' => false,
            ]
        );
    }

    /**
     * Realign the opening cut-off to a new date.
     *
     * Moves the Opening period end_date AND re-dates every opening-tagged row
     * (journal entries + opening stock movements) that sat on the old cut-off,
     * so daily transactions dated after the new cut-off become postable. The
     * locked state is never changed here. Idempotent — a no-op when the date is
     * already aligned. Only touches Opening rows, so it stays safe even after
     * daily entries exist.
     */
    public function realignCutoff(string $newCutoff): void
    {
        $period = $this->openingPeriod();

        $old = Carbon::parse($period->end_date)->toDateString();
        $new = Carbon::parse($newCutoff)->toDateString();

        if ($old === $new) {
            return;
        }

        DB::transaction(function () use ($period, $old, $new) {
            JournalEntry::where('reference_type', 'Opening')
                ->whereDate('date', $old)
                ->update(['date' => $new]);

            StockMovement::opening()
                ->whereDate('date', $old)
                ->update(['date' => $new]);

            $period->update(['end_date' => $new]);
        });
    }

    /**
     * Owner recovery path: temporarily unlock, realign the cut-off to a new
     * date, then re-lock — all audited via unlock_reason. Lets a shop that
     * locked itself out of "today" self-heal from the UI, no console needed.
     */
    public function reopenAndSetCutoff(int $userId, string $newCutoff, string $reason): AccountingPeriod
    {
        return DB::transaction(function () use ($userId, $newCutoff, $reason) {
            $this->unlockOpening($userId, $reason);
            $this->realignCutoff($newCutoff);

            return $this->lockOpening($userId);
        });
    }
}
