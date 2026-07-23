<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Adjustment\Services\PurchaseReturnService;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private PurchaseReturnService $returns,
    ) {}

    public function create()
    {
        return view('shop.return.purchase', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'paymentAccounts' => Account::cashOrBank()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_account_id' => ['nullable', 'exists:accounts,id'],
            'reduce_payable' => ['nullable', 'boolean'],
            'date' => ['required', 'date'],
        ]);

        try {
            $this->returns->returnPurchase($data['items'], [
                'date' => $data['date'],
                'refund_amount' => $data['refund_amount'] ?? 0,
                'refund_account_id' => $data['refund_account_id'] ?? null,
            ]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('purchases.index')->with('status', __('ui.common.saved'));
    }
}
