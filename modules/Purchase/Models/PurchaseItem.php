<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Product;

/**
 * @property int $id
 * @property int $purchase_id
 * @property int $product_id
 * @property string $qty
 * @property string $unit_cost
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PurchaseItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
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
