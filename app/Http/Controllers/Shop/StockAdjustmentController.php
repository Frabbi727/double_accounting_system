<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Product;
use Modules\Adjustment\Services\StockAdjustmentService;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private StockAdjustmentService $adjustments,
    ) {}

    public function create()
    {
        return view('shop.stock_loss.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        try {
            $this->adjustments->recordLoss(
                Product::findOrFail($data['product_id']),
                (float) $data['qty'],
                ['date' => $data['date'], 'reason' => $data['reason'] ?? null],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['qty' => $e->getMessage()]);
        }

        return redirect()->route('reports.stock')->with('status', __('ui.common.saved'));
    }
}
