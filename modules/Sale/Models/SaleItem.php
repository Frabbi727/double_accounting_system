<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Product;

/**
 * A sale line. cost_price is FROZEN at the moment of sale — it is copied from
 * the product's weighted-average cost then and never looked up again, so
 * historical profit does not change when the product cost later changes.
 */
class SaleItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'cost_price' => 'decimal:4',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Revenue of this line (qty × unit_price). */
    public function lineRevenue(): float
    {
        return round((float) $this->qty * (float) $this->unit_price, 2);
    }

    /** COGS of this line at the frozen cost (qty × cost_price). */
    public function lineCogs(): float
    {
        return round((float) $this->qty * (float) $this->cost_price, 2);
    }
}
