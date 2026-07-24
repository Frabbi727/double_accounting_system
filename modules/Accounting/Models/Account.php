<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Enums\AccountType;

/**
 * @property int $id
 * @property string $code
 * @property string $name_bn
 * @property string $name_en
 * @property string $name
 * @property \Modules\Accounting\Enums\AccountType $type
 * @property string $subtype
 * @property int|null $parent_id
 * @property bool $is_system
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Account code(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder|Account cashOrBank()
 */
class Account extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => AccountType::class,
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Locale-aware display name, backed by the name_bn / name_en columns.
     * Reading $account->name returns the string in the active locale, with
     * a fallback to the other language so it is never empty.
     */
    public function getNameAttribute(): string
    {
        $primary = app()->getLocale() === 'en' ? 'name_en' : 'name_bn';
        $fallback = $primary === 'name_en' ? 'name_bn' : 'name_en';

        return $this->attributes[$primary]
            ?? $this->attributes[$fallback]
            ?? '';
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Account> $query
     * @return \Illuminate\Database\Eloquent\Builder<Account>
     */
    public function scopeCode(\Illuminate\Database\Eloquent\Builder $query, string $code): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('code', $code);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Account> $query
     * @return \Illuminate\Database\Eloquent\Builder<Account>
     */
    public function scopeCashOrBank(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('subtype', ['cash', 'bank']);
    }

    /** System accounts (AR, AP, Inventory, Equity) must never be deleted. */
    public function isDeletable(): bool
    {
        return ! $this->is_system && ! $this->lines()->exists();
    }
}
