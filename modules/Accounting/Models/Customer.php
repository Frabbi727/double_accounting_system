<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property string|null $phone
 * @property string|null $address
 * @property string $credit_limit
 * @property string $default_discount_percent
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'default_discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->name_normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $customer->name)));
        });
    }

    /**
     * @return MorphMany<OpeningPartyBalance, $this>
     */
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
