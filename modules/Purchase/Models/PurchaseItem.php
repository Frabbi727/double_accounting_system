<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Product;

class PurchaseItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Goods value of this line, at the recorded invoice cost (before landed cost). */
    public function lineTotal(): float
    {
        return round((float) $this->qty * (float) $this->unit_cost, 2);
    }
}
