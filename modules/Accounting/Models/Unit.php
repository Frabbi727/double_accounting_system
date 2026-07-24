<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name_bn
 * @property string $name_en
 * @property string $name
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Unit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Locale-aware display name, backed by the name_bn / name_en columns.
     */
    public function getNameAttribute(): string
    {
        $primary = app()->getLocale() === 'en' ? 'name_en' : 'name_bn';
        $fallback = $primary === 'name_en' ? 'name_bn' : 'name_en';

        return $this->attributes[$primary]
            ?? $this->attributes[$fallback]
            ?? '';
    }
}
