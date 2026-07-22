<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMMUTABLE. Only LedgerService may create these. Never update the
 * date, description or lines after creation — post a reversal instead.
 */
class JournalEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_id !== null;
    }

    public function isReversal(): bool
    {
        return $this->reverses_id !== null;
    }

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function scopeOpening($query)
    {
        return $query->where('reference_type', 'Opening');
    }

    /** Entries that still have effect (not reversed, not reversals). */
    public function scopeLive($query)
    {
        return $query->whereNull('reversed_by_id')->whereNull('reverses_id');
    }
}
