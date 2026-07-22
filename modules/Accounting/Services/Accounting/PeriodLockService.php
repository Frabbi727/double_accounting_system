<?php

namespace Modules\Accounting\Services\Accounting;

use Modules\Accounting\Models\AccountingPeriod;

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
}
