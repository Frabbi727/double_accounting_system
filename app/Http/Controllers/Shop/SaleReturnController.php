<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Adjustment\Services\SaleReturnService;
use Modules\Sale\Models\Sale;

class SaleReturnController extends Controller
{
    public function __construct(
        private SaleReturnService $returns,
    ) {}

    public function create(Request $request): View
    {
        $sale = $request->filled('sale_id')
            ? Sale::with('items.product')->find($request->integer('sale_id'))
            : null;

        return view('shop.return.sale', [
            'sales' => Sale::latest('date')->latest('id')->limit(100)->get(),
            'sale' => $sale,
            'paymentAccounts' => Account::cashOrBank()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_account_id' => ['nullable', 'exists:accounts,id'],
            'date' => ['required', 'date'],
        ]);

        // Only lines with a positive return qty.
        $items = array_values(array_filter(
            $data['items'],
            fn ($i) => (float) $i['qty'] > 0
        ));

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => __('adjustment.errors.no_items')]);
        }

        try {
            $this->returns->returnSale(
                Sale::where('id', $data['sale_id'])->firstOrFail(),
                $items,
                [
                    'date' => $data['date'],
                    'refund_amount' => $data['refund_amount'] ?? 0,
                    'refund_account_id' => $data['refund_account_id'] ?? null,
                ],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('sales.index')->with('status', __('ui.common.saved'));
    }
}
