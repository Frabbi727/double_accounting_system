<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Enums\MovementType;

/**
 * IMMUTABLE. qty is signed: positive for stock in, negative for stock out.
 * Corrections are made by inserting an opposite movement, never by editing.
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeOpening($query)
    {
        return $query->where('reference_type', 'Opening');
    }
}
