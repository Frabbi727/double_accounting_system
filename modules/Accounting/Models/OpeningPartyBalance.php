<?php

namespace Modules\Accounting\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $party_type
 * @property int $party_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon $original_date
 * @property string|null $reference
 * @property int $journal_entry_id
 * @property \Illuminate\Support\Carbon|null $reversed_at
 * @property string|null $reversal_reason
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OpeningPartyBalance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
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
