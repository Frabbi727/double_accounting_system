<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** "Category > Sub-category" when this row is a sub-category, else just the name. */
    public function getFullNameAttribute(): string
    {
        return $this->parent
            ? $this->parent->name.' > '.$this->name
            : $this->name;
    }

    /** Null for a top-level category; set for a sub-category. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    /** Sub-categories under a top-level category. */
    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
