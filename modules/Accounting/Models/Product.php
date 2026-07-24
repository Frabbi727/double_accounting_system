<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $name_normalized
 * @property string|null $sku
 * @property int|null $product_category_id
 * @property string $unit
 * @property string $cost_price
 * @property string $sale_price
 * @property int $reorder_level
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Keep the normalized name in sync for duplicate detection.
        static::saving(function (Product $product) {
            $product->name_normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $product->name)));
        });
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Current stock, always derived from stock_movements.
     * There is no cached column — this is the single source of truth.
     */
    public function currentStock(?string $asOf = null): float
    {
        $query = $this->movements();

        if ($asOf !== null) {
            $query->whereDate('date', '<=', $asOf);
        }

        return (float) $query->sum('qty');
    }

    /** Stock valued at the current weighted-average cost. */
    public function stockValue(?string $asOf = null): float
    {
        return round($this->currentStock($asOf) * (float) $this->cost_price, 2);
    }

    public function isLowStock(): bool
    {
        return $this->reorder_level > 0
            && $this->currentStock() <= $this->reorder_level;
    }

    public function openingMovement(): ?StockMovement
    {
        return $this->movements()
            ->where('reference_type', 'Opening')
            ->first();
    }
}
