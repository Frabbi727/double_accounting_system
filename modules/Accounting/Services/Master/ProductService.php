<?php

namespace Modules\Accounting\Services\Master;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\MovementType;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\StockMovement;
use Modules\Accounting\Services\Accounting\OpeningEntryService;

class ProductService
{
    public function __construct(
        private OpeningEntryService $opening,
    ) {}

    /**
     * Create a product, optionally with opening stock entered on the same form.
     *
     * Two things happen for opening stock, and they must agree:
     *   1. a stock_movements row  (qty × unit_cost)
     *   2. a journal entry        Debit 1040 Inventory / Credit 3010 Equity
     *
     * If these two ever disagree, the balance sheet breaks — so they are
     * created in one transaction from one computed value.
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {

            $product = Product::create([
                'name' => $data['name'],
                'product_category_id' => $data['product_category_id'] ?? null,
                'unit' => $data['unit'] ?? 'pcs',
                'cost_price' => $data['cost_price'],
                'sale_price' => $data['sale_price'],
                'reorder_level' => $data['reorder_level'] ?? 0,
            ]);

            // SKU is system-generated (not user-entered) and unique — derived
            // from the auto-increment id so no collision is possible.
            $product->update(['sku' => $this->generateSku($product->id)]);

            $qty = (float) ($data['opening_qty'] ?? 0);

            if ($qty > 0) {
                $this->addOpeningStock(
                    product: $product,
                    qty: $qty,
                    unitCost: (float) ($data['opening_cost'] ?? $data['cost_price']),
                    date: $data['opening_date'] ?? config('shop.cutoff_date'),
                );
            }

            return $product->fresh();
        });
    }

    /** System SKU: "P" + zero-padded product id (e.g. P00042). Always unique. */
    private function generateSku(int $id): string
    {
        return 'P'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function update(Product $product, array $data): Product
    {
        // Opening stock is not editable here — use correctOpeningStock().
        // SKU is system-generated and immutable — never touched on update.
        $product->update([
            'name' => $data['name'],
            'product_category_id' => $data['product_category_id'] ?? null,
            'unit' => $data['unit'] ?? 'pcs',
            'sale_price' => $data['sale_price'],
            'reorder_level' => $data['reorder_level'] ?? 0,
            // cost_price is NOT updated here — it is maintained by CostingService
            // as a weighted average on every stock IN.
        ]);

        return $product->fresh();
    }

    public function addOpeningStock(Product $product, float $qty, float $unitCost, string $date): StockMovement
    {
        if ($unitCost <= 0) {
            // A zero cost would make COGS zero and report the entire sale as profit.
            throw new \InvalidArgumentException(__('accounting.errors.opening_cost_positive'));
        }

        $value = round($qty * $unitCost, 2);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => MovementType::In,
            'qty' => $qty,            // positive = in
            'unit_cost' => $unitCost,
            'reference_type' => 'Opening',
            'reference_id' => $product->id,
            'date' => $date,
            'created_by' => auth()->id(),
        ]);

        // The journal must use exactly the same value as the movement.
        $this->opening->post(
            account: $this->opening->inventoryAccount(),
            amount: $value,
            date: config('shop.cutoff_date'),
            source: $product,
        );

        // Opening cost becomes the starting weighted-average cost.
        $product->update(['cost_price' => $unitCost]);

        return $movement;
    }

    /**
     * Correct opening stock: reverse both the movement and the journal entry.
     */
    public function correctOpeningStock(
        Product $product,
        float $newQty,
        float $newUnitCost,
        string $reason,
    ): void {
        DB::transaction(function () use ($product, $newQty, $newUnitCost, $reason) {

            $old = $product->openingMovement();

            if ($old) {
                // Opposite movement cancels the original; the original stays on record.
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => MovementType::Adjustment,
                    'qty' => -$old->qty,
                    'unit_cost' => $old->unit_cost,
                    'reference_type' => 'OpeningReversal',
                    'reference_id' => $product->id,
                    'date' => $old->date->toDateString(),
                    'created_by' => auth()->id(),
                ]);
            }

            $this->opening->correct(
                source: $product,
                account: $this->opening->inventoryAccount(),
                newAmount: round($newQty * $newUnitCost, 2),
                date: config('shop.cutoff_date'),
                reason: $reason,
            );

            StockMovement::create([
                'product_id' => $product->id,
                'type' => MovementType::In,
                'qty' => $newQty,
                'unit_cost' => $newUnitCost,
                'reference_type' => 'Opening',
                'reference_id' => $product->id,
                'date' => config('shop.cutoff_date'),
                'created_by' => auth()->id(),
            ]);

            $product->update(['cost_price' => $newUnitCost]);
        });
    }

    public function findSimilar(string $name, ?int $excludeId = null): ?Product
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));

        return Product::where('name_normalized', $normalized)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }
}
