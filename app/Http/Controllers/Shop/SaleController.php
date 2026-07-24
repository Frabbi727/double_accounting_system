<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $sales,
    ) {}

    public function index(): View
    {
        return view('shop.sale.index', [
            'sales' => Sale::latest('date')->latest('id')->limit(50)->get(),
        ]);
    }

    /**
     * Printable invoice (FR-28). Revenue-side only — no cost/profit — so it is
     * safe for the salesperson to print (NFR-07). ?format=receipt renders a
     * narrow thermal-printer layout.
     */
    public function print(Sale $sale): View
    {
        return view('shop.sale.print', [
            'sale' => $sale->load('items.product', 'customer'),
            'format' => request('format') === 'receipt' ? 'receipt' : 'a4',
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['items.product.category.parent', 'customer', 'creator']);

        // Find the revenue journal entry to see which cash/bank account was used.
        $journalEntry = \Modules\Accounting\Models\JournalEntry::where('reference_type', 'Sale')
            ->where('reference_id', $sale->id)
            ->with('lines.account')
            ->first();

        // Find the cash or bank line to determine the deposit account.
        $paymentLine = $journalEntry
            ? $journalEntry->lines->first(fn($line) => $line->debit > 0 && in_array($line->account->subtype, ['cash', 'bank']))
            : null;

        $paymentAccount = $paymentLine ? $paymentLine->account : null;

        // If the user has permission to see costs, load the ledger entries (Revenue + COGS).
        $ledgerEntries = [];
        if (auth()->user()->can('cost.view')) {
            $ledgerEntries = \Modules\Accounting\Models\JournalEntry::where(function ($query) use ($sale) {
                $query->where(fn($q) => $q->where('reference_type', 'Sale')->where('reference_id', $sale->id))
                      ->orWhere(fn($q) => $q->where('reference_type', 'SaleCOGS')->where('reference_id', $sale->id));
            })->with('lines.account')->get();
        }

        return view('shop.sale.show', [
            'sale' => $sale,
            'paymentAccount' => $paymentAccount,
            'ledgerEntries' => $ledgerEntries,
        ]);
    }

    public function create(): View
    {
        $customers = Customer::orderBy('name')->get();

        return view('shop.sale.create', [
            'products' => Product::where('is_active', true)->with('category.parent')->orderBy('name')->get(),
            'customers' => $customers,
            // Cash/bank accounts the paid amount can land in ("cash drawer").
            'accounts' => Account::cashOrBank()->orderBy('code')->get(),
            'defaultAccountId' => Account::code('1010')->value('id'),
            // id => saved default discount %, to pre-fill the bill discount on select.
            'customerDiscounts' => $customers->mapWithKeys(
                fn (Customer $c) => [$c->id => (float) $c->default_discount_percent]
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['nullable', 'exists:accounts,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->sales->create($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            // Service-level guards (no stock, unbalanced, etc.) → readable message.
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect()->route('sales.index')->with('status', __('ui.common.saved'));
    }
}
