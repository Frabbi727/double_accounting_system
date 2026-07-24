<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Incentive\Models\PartyIncentive;
use Modules\Incentive\Services\IncentiveService;

/**
 * Incentives (FR-49/50): a bonus received from a supplier is our income, one
 * given to a customer is our expense. Now attributed to the party and either
 * paid/received in cash or settled against that party's due — the latter flows
 * into the party statement/aging via the ledger. Records to party_incentives.
 */
class IncentiveController extends Controller
{
    public function __construct(
        private IncentiveService $incentives,
        private ReportService $reports,
        private LedgerService $ledger,
    ) {}

    public function index(): \Illuminate\View\View
    {
        $entries = PartyIncentive::where('kind', 'incentive')
            ->latest('date')->latest('id')->limit(100)->get();

        return view('shop.incentive.index', [
            'entries' => $entries,
            'remaining' => $this->remainingDues($entries),
        ]);
    }

    /**
     * Printable detail voucher for one incentive — the full tracking: party,
     * how the amount was computed, how it was settled, the party's live
     * remaining due, and the exact debit/credit it posted.
     */
    public function show(PartyIncentive $incentive): \Illuminate\View\View
    {
        abort_if($incentive->kind !== 'incentive', 404);

        $incentive->load('journalEntry.lines.account', 'settleAccount', 'creator');

        $partyType = $incentive->party_type;
        $remainingDue = null;

        if ($incentive->party_id && in_array($partyType, ['customer', 'supplier'])) {
            /** @var 'customer'|'supplier' $partyType */
            $remainingDue = $this->reports->partyDue($partyType, $incentive->party_id);
        }

        return view('shop.incentive.voucher', compact('incentive', 'remainingDue'));
    }

    public function create(): \Illuminate\View\View
    {
        $accounts = Account::cashOrBank()->orderBy('code')->get();

        return view('shop.incentive.create', [
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'accounts' => $accounts,
            'accountBalances' => $accounts->mapWithKeys(fn ($a) => [$a->id => $this->ledger->balance($a)]),
            'customerDues' => collect($this->reports->partyDues('customer'))->pluck('due', 'id'),
            'supplierDues' => collect($this->reports->partyDues('supplier'))->pluck('due', 'id'),
            'customerDocs' => $this->reports->partyDocuments('customer'),
            'supplierDocs' => $this->reports->partyDocuments('supplier'),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:received,given'],
            'settle_mode' => ['required', 'in:cash,due'],
            'party_id' => ['nullable', 'integer', 'required_if:settle_mode,due'],
            'basis' => ['required', 'in:fixed,pct_of_due,pct_of_invoice,pct_of_sales'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'required_if:basis,fixed'],
            'rate' => ['nullable', 'numeric', 'gt:0', 'required_unless:basis,fixed'],
            'ref_doc_id' => ['nullable', 'integer', 'required_if:basis,pct_of_invoice'],
            'period_from' => ['nullable', 'date', 'required_if:basis,pct_of_sales'],
            'period_to' => ['nullable', 'date', 'required_if:basis,pct_of_sales', 'after_or_equal:period_from'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Direction fixes which document type an invoice-basis refers to.
        $data['ref_doc_type'] = $data['direction'] === 'received' ? 'Purchase' : 'Sale';

        try {
            $this->incentives->record($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('incentives.index')->with('status', __('ui.common.saved'));
    }

    /**
     * Live remaining due for each row's party — the "baki koto" column. Cached
     * per party so one supplier's several rows don't re-query.
     *
     * @param iterable<mixed, PartyIncentive> $entries
     * @return array<string, float>
     */
    private function remainingDues(iterable $entries): array
    {
        $remaining = [];
        foreach ($entries as $e) {
            if (! $e->party_id || ! in_array($e->party_type, ['customer', 'supplier'])) {
                continue;
            }
            $key = $e->party_type.':'.$e->party_id;
            /** @var 'customer'|'supplier' $partyType */
            $partyType = $e->party_type;
            $remaining[$key] ??= $this->reports->partyDue($partyType, $e->party_id);
        }

        return $remaining;
    }
}
