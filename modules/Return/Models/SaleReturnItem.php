<?php

namespace Modules\Return\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Product;
use Modules\Sale\Models\SaleItem;

/**
 * A single returned line. qty / unit_price / cost_price are snapshots taken at
 * return time (mirroring how sale_items freezes its own facts).
 *
 * @property int $id
 * @property int $sale_return_id
 * @property int $sale_item_id
 * @property int $product_id
 * @property string $qty
 * @property string $unit_price
 * @property string $cost_price
 */
class SaleReturnItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:4',
    ];

    /** @return BelongsTo<SaleReturn, $this> */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Returned amount of this line (qty × unit_price). */
    public function lineAmount(): float
    {
        return round((float) $this->qty * (float) $this->unit_price, 2);
    }

    /**
     * Total qty already returned against a sale line across all COMPLETED
     * returns. A cancelled return frees its qty back. Used by the cumulative
     * over-return guard in ReturnService.
     */
    public static function alreadyReturnedQty(int $saleItemId): float
    {
        return (float) static::query()
            ->where('sale_item_id', $saleItemId)
            ->whereHas('saleReturn', fn ($q) => $q->where('status', 'completed'))
            ->sum('qty');
    }
}
