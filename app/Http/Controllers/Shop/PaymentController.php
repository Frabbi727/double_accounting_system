<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\PaymentService;

class PaymentController extends Controller
{
    /** Ledger reference types that represent a party payment. */
    private const PAYMENT_TYPES = ['PaymentIn', 'PaymentOut'];

    public function __construct(
        private PaymentService $payments,
        private ReportService $reports,
        private LedgerService $ledger,
    ) {}

    /**
     * Payments have no table of their own — each one is a journal entry keyed
     * to the party by reference_type (PaymentIn/PaymentOut) + reference_id.
     * The list resolves the party name and their live remaining due per row.
     */
    public function index(): \Illuminate\View\View
    {
        $entries = JournalEntry::whereIn('reference_type', self::PAYMENT_TYPES)
            ->with('lines.account', 'creator')
            ->latest('date')->latest('id')->limit(100)->get();

        $customers = Customer::pluck('name', 'id');
        $suppliers = Supplier::pluck('name', 'id');

        return view('shop.payment.index', [
            'entries' => $entries,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'remaining' => $this->remainingDues($entries),
            'cashAccount' => fn (JournalEntry $e) => $this->cashAccount($e),
        ]);
    }

    /**
     * Printable detail voucher for one payment — who it was to/from, the
     * cash/bank account it moved through, the party's live remaining due, and
     * the exact debit/credit it posted to the ledger.
     */
    public function show(JournalEntry $payment): \Illuminate\View\View
    {
        abort_if(! in_array($payment->reference_type, self::PAYMENT_TYPES, true), 404);

        $payment->load('lines.account', 'creator');

        $isReceived = $payment->reference_type === 'PaymentIn';
        /** @var 'customer'|'supplier' $partyType */
        $partyType = $isReceived ? 'customer' : 'supplier';
        $party = ($isReceived ? Customer::class : Supplier::class)::find($payment->reference_id);

        $referenceId = (int) $payment->reference_id;
        $remainingDue = $party && $referenceId
            ? $this->reports->partyDue($partyType, $referenceId)
            : null;

        return view('shop.payment.voucher', [
            'payment' => $payment,
            'isReceived' => $isReceived,
            'party' => $party,
            'cashAccount' => $this->cashAccount($payment),
            'remainingDue' => $remainingDue,
        ]);
    }

    public function create(Request $request): \Illuminate\View\View
    {
        // Optional pre-fill from a "settle" link on the due list / statement.
        $direction = $request->input('direction') === 'made' ? 'made' : 'received';
        $partyId = (int) $request->input('party_id');

        // id => current due maps, so the form can cap the amount for whichever
        // party is picked (the frontend half of the overpayment guard).
        $customerDues = collect($this->reports->partyDues('customer'))->pluck('due', 'id');
        $supplierDues = collect($this->reports->partyDues('supplier'))->pluck('due', 'id');

        $due = $partyId
            ? ($direction === 'made' ? $supplierDues : $customerDues)->get($partyId)
            : null;

        $paymentAccounts = Account::cashOrBank()->orderBy('code')->get();

        return view('shop.payment.create', [
            'customers' => Customer::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'paymentAccounts' => $paymentAccounts,
            'paymentAccountBalances' => $paymentAccounts->mapWithKeys(
                fn ($a) => [$a->id => $this->ledger->balance($a)]
            ),
            'customerDues' => $customerDues,
            'supplierDues' => $supplierDues,
            'prefillDirection' => $direction,
            'prefillPartyId' => $partyId ?: null,
            'prefillDue' => $due,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:received,made'],
            'party_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'payment_account_id' => ['nullable', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'amount' => $data['amount'],
            'date' => $data['date'],
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        try {
            if ($data['direction'] === 'received') {
                /** @var \Modules\Accounting\Models\Customer $customer */
                $customer = Customer::findOrFail($data['party_id']);
                $this->payments->receiveFromCustomer($customer, $payload);
            } else {
                /** @var \Modules\Accounting\Models\Supplier $supplier */
                $supplier = Supplier::findOrFail($data['party_id']);
                $this->payments->payToSupplier($supplier, $payload);
            }
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('payments.create')->with('status', __('ui.common.saved'));
    }

    /**
     * The cash/bank side of a payment: for a receipt it's the debited account,
     * for a supplier payment it's the credited one (the other line is the
     * receivable/payable control account).
     */
    private function cashAccount(JournalEntry $entry): ?Account
    {
        $received = $entry->reference_type === 'PaymentIn';

        $line = $entry->lines->first(
            fn ($l) => $received ? (float) $l->debit > 0 : (float) $l->credit > 0
        );

        return $line?->account;
    }

    /**
     * Live remaining due per party referenced in the list, keyed "type:id",
     * deduplicated so each party is queried at most once.
     *
     * @param iterable<mixed, JournalEntry> $entries
     * @return array<string, float>
     */
    private function remainingDues(iterable $entries): array
    {
        $remaining = [];
        foreach ($entries as $e) {
            /** @var 'customer'|'supplier' $party */
            $party = $e->reference_type === 'PaymentIn' ? 'customer' : 'supplier';
            $referenceId = (int) $e->reference_id;
            if (!$referenceId) {
                continue;
            }
            $key = $party.':'.$referenceId;
            $remaining[$key] ??= $this->reports->partyDue($party, $referenceId);
        }

        return $remaining;
    }
}
