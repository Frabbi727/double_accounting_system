<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'default_discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->name_normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $customer->name)));
        });
    }

    public function openingBalances(): MorphMany
    {
        return $this->morphMany(OpeningPartyBalance::class, 'party');
    }

    /** Live (non-reversed) opening balance total. */
    public function openingBalance(): float
    {
        return (float) $this->openingBalances()->whereNull('reversed_at')->sum('amount');
    }
}
