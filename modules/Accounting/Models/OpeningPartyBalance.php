<?php

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningPartyBalance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function party()
    {
        return $this->morphTo();
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** Age in days as of a given date — drives the aging report. */
    public function ageInDays(?string $asOf = null): int
    {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        return (int) $this->original_date->diffInDays($asOf);
    }
}
