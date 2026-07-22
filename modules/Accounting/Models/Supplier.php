<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Supplier extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier) {
            $supplier->name_normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $supplier->name)));
        });
    }

    public function openingBalances(): MorphMany
    {
        return $this->morphMany(OpeningPartyBalance::class, 'party');
    }

    public function openingBalance(): float
    {
        return (float) $this->openingBalances()->whereNull('reversed_at')->sum('amount');
    }
}
