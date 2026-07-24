<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Enums\MovementType;

/**
 * IMMUTABLE. qty is signed: positive for stock in, negative for stock out.
 * Corrections are made by inserting an opposite movement, never by editing.
 */
/**
 * @property int $id
 * @property int $product_id
 * @property \Modules\Accounting\Enums\MovementType $type
 * @property string $qty
 * @property string|null $unit_cost
 * @property string $reference_type
 * @property int|null $reference_id
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|StockMovement opening()
 */
class StockMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => MovementType::class,
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'date' => 'date',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<StockMovement> $query
     * @return \Illuminate\Database\Eloquent\Builder<StockMovement>
     */
    public function scopeOpening(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('reference_type', 'Opening');
    }
}
