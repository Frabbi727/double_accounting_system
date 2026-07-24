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
 * @property int $payment_term_days
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Supplier extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier) {
            $supplier->name_normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $supplier->name)));
        });
    }

    /**
     * @return MorphMany<OpeningPartyBalance, $this>
     */
    public function openingBalances(): MorphMany
    {
        return $this->morphMany(OpeningPartyBalance::class, 'party');
    }

    public function openingBalance(): float
    {
        return (float) $this->openingBalances()->whereNull('reversed_at')->sum('amount');
    }
}
