<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Enums\AccountType;

class Account extends Model
{
    use HasFactory;

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

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function scopeCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeCashOrBank($query)
    {
        return $query->whereIn('subtype', ['cash', 'bank']);
    }

    /** System accounts (AR, AP, Inventory, Equity) must never be deleted. */
    public function isDeletable(): bool
    {
        return ! $this->is_system && ! $this->lines()->exists();
    }
}
