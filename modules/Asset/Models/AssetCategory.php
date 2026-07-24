<?php

namespace Modules\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\Account;

/**
 * @property int $id
 * @property string $name_bn
 * @property string $name_en
 * @property string $name
 * @property int|null $account_id
 * @property bool $is_system
 * @property bool $is_active
 * @property int $sort
 */
class AssetCategory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Locale-aware display name, backed by name_bn / name_en. */
    public function getNameAttribute(): string
    {
        $primary = app()->getLocale() === 'en' ? 'name_en' : 'name_bn';
        $fallback = $primary === 'name_en' ? 'name_bn' : 'name_en';

        return $this->attributes[$primary]
            ?? $this->attributes[$fallback]
            ?? '';
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
