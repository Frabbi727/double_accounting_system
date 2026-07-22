<?php

namespace Modules\Accounting\Services\Inventory;

use Modules\Accounting\Enums\MovementType;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\StockMovement;

/**
 * The single gateway for stock movements.
 *
 * Every quantity change goes through stockIn() / stockOut(), which keep the
 * weighted-average cost in sync and enforce the negative-stock guard.
 * stock_movements is append-only — corrections are opposite movements.
 *
 * Callers MUST wrap these in their own DB::transaction() together with the
 * matching ledger entry, so stock and books never drift apart.
 */
class InventoryService
{
    public function __construct(
        private CostingService $costing,
    ) {}

    /**
     * Receive stock. Recalculates the product's weighted-average cost and
     * records a positive movement. Returns the movement.
     */
    public function stockIn(
        Product $product,
        float $qty,
        float $unitCost,
        string $referenceType,
        ?int $referenceId,
        string $date,
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException(__('accounting.errors.stock_in_qty'));
        }
        if ($unitCost <= 0) {
            throw new \InvalidArgumentException(__('accounting.errors.stock_in_cost'));
        }

        $newCost = $this->costing->costAfterStockIn($product, $qty, $unitCost);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => MovementType::In,
            'qty' => $qty,             // positive = in
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'date' => $date,
            'created_by' => auth()->id(),
        ]);

        $product->update(['cost_price' => $newCost]);

        return $movement;
    }

    /**
     * Issue stock (sale, loss, adjustment out). Records a negative movement
     * valued at the current weighted-average cost — this frozen unit_cost is
     * what COGS is later computed from. Returns the movement.
     */
    public function stockOut(
        Product $product,
        float $qty,
        string $referenceType,
        ?int $referenceId,
        string $date,
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException(__('accounting.errors.stock_out_qty'));
        }

        if (! config('shop.allow_negative_stock') && ! $this->checkAvailability($product, $qty)) {
            throw new \RuntimeException(__('accounting.errors.insufficient_stock', [
                'product' => $product->name,
                'available' => (float) $product->currentStock(),
                'requested' => $qty,
            ]));
        }

        return StockMovement::create([
            'product_id' => $product->id,
            'type' => MovementType::Out,
            'qty' => -$qty,            // negative = out
            'unit_cost' => $product->cost_price,   // frozen cost for COGS
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'date' => $date,
            'created_by' => auth()->id(),
        ]);
    }

    public function checkAvailability(Product $product, float $qty): bool
    {
        return $product->currentStock() >= $qty;
    }
}
