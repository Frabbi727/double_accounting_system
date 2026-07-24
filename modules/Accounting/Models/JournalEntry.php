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
/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property string $reference_type
 * @property int|null $reference_id
 * @property string $description
 * @property int|null $reversed_by_id
 * @property int|null $reverses_id
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|JournalEntry opening()
 * @method static \Illuminate\Database\Eloquent\Builder|JournalEntry live()
 */
class JournalEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
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

    /**
     * @param \Illuminate\Database\Eloquent\Builder<JournalEntry> $query
     * @return \Illuminate\Database\Eloquent\Builder<JournalEntry>
     */
    public function scopeOpening(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('reference_type', 'Opening');
    }

    /**
     * Entries that still have effect (not reversed, not reversals).
     *
     * @param \Illuminate\Database\Eloquent\Builder<JournalEntry> $query
     * @return \Illuminate\Database\Eloquent\Builder<JournalEntry>
     */
    public function scopeLive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('reversed_by_id')->whereNull('reverses_id');
    }
}
