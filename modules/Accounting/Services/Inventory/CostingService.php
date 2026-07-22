<?php

namespace Modules\Accounting\Services\Inventory;

use Modules\Accounting\Models\Product;

/**
 * Weighted-average costing.
 *
 * The only costing method the system implements. On every stock IN, a
 * product's unit cost is recalculated so that historical valuations stay
 * consistent:
 *
 *   newCost = (oldQty × oldCost + inQty × inCost) ÷ (oldQty + inQty)
 *
 * This class is pure calculation — it does not touch the database. The
 * caller (InventoryService) persists the result.
 */
class CostingService
{
    /** Costs are carried at 4 decimal places. */
    private const SCALE = 4;

    public function weightedAverage(float $oldQty, float $oldCost, float $inQty, float $inCost): float
    {
        $totalQty = $oldQty + $inQty;

        // No stock on hand (or a fully-drawn-down product) — the incoming
        // cost simply becomes the new cost.
        if ($totalQty <= 0) {
            return round($inCost, self::SCALE);
        }

        return round(
            (($oldQty * $oldCost) + ($inQty * $inCost)) / $totalQty,
            self::SCALE
        );
    }

    /** The product's new weighted-average cost after receiving inQty at inCost. */
    public function costAfterStockIn(Product $product, float $inQty, float $inCost): float
    {
        return $this->weightedAverage(
            $product->currentStock(),
            (float) $product->cost_price,
            $inQty,
            $inCost,
        );
    }
}
