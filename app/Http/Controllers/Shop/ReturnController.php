<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Support\ReturnPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\StockMovement;
use Modules\Return\Models\SaleReturn;
use Modules\Return\Models\SaleReturnItem;
use Modules\Return\Services\ReturnService;
use Modules\Sale\Models\Sale;

/**
 * Product Return Management (requirement §2). First-class return documents made
 * against a sale invoice: list, details, create (partial + multiple), cancel.
 */
class ReturnController extends Controller
{
    public function __construct(
        private ReturnService $returns,
    ) {}

    /** The Return List. */
    public function index(): View
    {
        $returns = SaleReturn::with(['customer', 'sale', 'creator', 'revenueEntry.lines.account', 'refundAccount'])
            ->withCount('items')
            ->latest('date')
            ->latest('id')
            ->paginate(30);

        return view('shop.return.index', ['returns' => $returns]);
    }

    /** Two-step create form: pick a sale (?sale_id), then enter return qtys. */
    public function create(Request $request): View
    {
        $sale = $request->filled('sale_id')
            ? Sale::with('items.product')->find($request->integer('sale_id'))
            : null;

        $returnable = [];
        if ($sale) {
            foreach ($sale->items as $item) {
                $returnable[$item->id] = round(
                    (float) $item->qty - SaleReturnItem::alreadyReturnedQty($item->id),
                    3
                );
            }
        }

        return view('shop.return.create', [
            'sales' => Sale::latest('date')->latest('id')->limit(100)->get(),
            'sale' => $sale,
            'returnable' => $returnable,
            'paymentAccounts' => Account::cashOrBank()->orderBy('code')->get(),
            'defaultAccountId' => Account::where('code', '1010')->value('id'),
            'policy' => ReturnPolicy::discountPolicy(),
            'hasDiscount' => $sale ? ((float) $sale->discount > 0 || $sale->itemDiscount() > 0) : false,
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
            'refund_account_id' => ['required', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'deduction_type' => ['required', 'in:none,fixed,percent'],
            'deduction_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Only lines with a positive return qty.
        $items = array_values(array_filter(
            $data['items'],
            fn ($i) => (float) $i['qty'] > 0
        ));

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => __('return.errors.no_items')]);
        }

        try {
            $return = $this->returns->create(
                Sale::where('id', $data['sale_id'])->firstOrFail(),
                $items,
                [
                    'date' => $data['date'],
                    'refund_amount' => $data['refund_amount'] ?? null,
                    'refund_account_id' => $data['refund_account_id'],
                    'reason' => $data['reason'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'deduction_type' => $data['deduction_type'],
                    'deduction_value' => $data['deduction_value'] ?? 0,
                ],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', __('ui.common.saved'));
    }

    /** The Return Details page. */
    public function show(SaleReturn $return): View
    {
        $return->load([
            'items.product',
            'sale.customer',
            'customer',
            'creator',
            'canceller',
            'refundAccount',
            'revenueEntry.lines.account',
            'revenueEntry.reversedBy.lines.account',
            'cogsEntry.lines.account',
            'cogsEntry.reversedBy.lines.account',
        ]);

        $productIds = $return->items->pluck('product_id')->all();
        $movements = StockMovement::with('product')
            ->whereIn('reference_type', ['SaleReturn', 'SaleReturnCancel'])
            ->where('reference_id', $return->sale_id)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get();

        return view('shop.return.show', [
            'return' => $return,
            'movements' => $movements,
        ]);
    }

    public function cancel(Request $request, SaleReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->returns->cancel($return, $data['cancel_reason']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return redirect()->route('returns.show', $return)
                ->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('returns.show', $return)->with('status', __('ui.common.saved'));
    }
}
