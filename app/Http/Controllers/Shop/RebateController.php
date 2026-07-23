<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Incentive\Services\RebateService;

/**
 * Rebate (FR-53): a post-purchase discount that lowers the cost of goods
 * still on hand — not income. Owner-only, as it adjusts inventory valuation.
 */
class RebateController extends Controller
{
    public function __construct(
        private RebateService $rebates,
    ) {}

    public function create()
    {
        return view('shop.rebate.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'accounts' => Account::cashOrBank()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'reduce_payable' => ['nullable', 'boolean'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $product = Product::findOrFail($data['product_id']);
            $this->rebates->applyToProduct($product, (float) $data['amount'], [
                'date' => $data['date'],
                'reduce_payable' => (bool) ($data['reduce_payable'] ?? false),
                'account_id' => $data['account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('rebates.create')->with('status', __('ui.common.saved'));
    }
}
