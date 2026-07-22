<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $guarded = [];

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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
