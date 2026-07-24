<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Incentive\Models\PartyIncentive;
use Modules\Incentive\Services\RebateService;

/**
 * Rebate (FR-53): a post-purchase discount that lowers the cost of goods still
 * on hand — not income. Owner-only, as it adjusts inventory valuation. Can be
 * received in cash or netted against a supplier's payable; records to
 * party_incentives with the target product.
 */
class RebateController extends Controller
{
    public function __construct(
        private RebateService $rebates,
        private ReportService $reports,
    ) {}

    public function index(): View
    {
        $entries = PartyIncentive::where('kind', 'rebate')
            ->with('product')->latest('date')->latest('id')->limit(100)->get();

        return view('shop.rebate.index', [
            'entries' => $entries,
            'remaining' => $this->remainingDues($entries),
        ]);
    }

    /**
     * Printable detail voucher for one rebate — the target product and its
     * stock value, how the amount was computed, how it was settled, the
     * supplier's live remaining due, and the exact debit/credit it posted.
     * Reuses the shared incentive voucher view.
     */
    public function show(PartyIncentive $rebate): View
    {
        abort_if($rebate->kind !== 'rebate', 404);

        $rebate->load('journalEntry.lines.account', 'product', 'settleAccount', 'creator');

        $remainingDue = $rebate->party_id
            ? $this->reports->partyDue('supplier', $rebate->party_id)
            : null;

        return view('shop.incentive.voucher', [
            'incentive' => $rebate,
            'remainingDue' => $remainingDue,
        ]);
    }

    public function create(): View
    {
        $accounts = Account::cashOrBank()->orderBy('code')->get();

        return view('shop.rebate.create', [
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'accounts' => $accounts,
            'supplierDues' => collect($this->reports->partyDues('supplier'))->pluck('due', 'id'),
            'supplierDocs' => $this->reports->partyDocuments('supplier'),
            'productValues' => Product::where('is_active', true)->get()
                ->mapWithKeys(fn ($p) => [$p->id => round($p->currentStock() * (float) $p->cost_price, 2)]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'settle_mode' => ['required', 'in:cash,due'],
            'party_id' => ['nullable', 'integer', 'required_if:settle_mode,due'],
            'basis' => ['required', 'in:fixed,pct_of_product_value,pct_of_invoice,pct_of_due,pct_of_sales'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'required_if:basis,fixed'],
            'rate' => ['nullable', 'numeric', 'gt:0', 'required_unless:basis,fixed'],
            'ref_doc_id' => ['nullable', 'integer', 'required_if:basis,pct_of_invoice'],
            'period_from' => ['nullable', 'date', 'required_if:basis,pct_of_sales'],
            'period_to' => ['nullable', 'date', 'required_if:basis,pct_of_sales', 'after_or_equal:period_from'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // A rebate always comes off a purchase from the supplier.
        $data['ref_doc_type'] = 'Purchase';

        try {
            $this->rebates->record($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('rebates.index')->with('status', __('ui.common.saved'));
    }

    /**
     * @param iterable<\Modules\Incentive\Models\PartyIncentive> $entries
     * @return array<string, float>
     */
    private function remainingDues(iterable $entries): array
    {
        $remaining = [];
        foreach ($entries as $e) {
            if (! $e->party_id) {
                continue;
            }
            $key = 'supplier:'.$e->party_id;
            $remaining[$key] ??= $this->reports->partyDue('supplier', $e->party_id);
        }

        return $remaining;
    }
}
