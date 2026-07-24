<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Finance\Services\TransferService;

class TransferController extends Controller
{
    public function __construct(
        private TransferService $transfers,
        private LedgerService $ledger,
    ) {}

    /**
     * Transfers have no table of their own — each is a journal entry keyed by
     * reference_type = 'Transfer'. The from/to accounts are read back off the
     * double entry (credit line = source, debit line = destination).
     */
    public function index(): View
    {
        $entries = JournalEntry::where('reference_type', 'Transfer')
            ->with('lines.account', 'creator')
            ->latest('date')->latest('id')->limit(100)->get();

        return view('shop.transfer.index', [
            'entries' => $entries,
            'fromAccount' => fn (JournalEntry $e) => $this->fromAccount($e),
            'toAccount' => fn (JournalEntry $e) => $this->toAccount($e),
        ]);
    }

    /**
     * Printable audit voucher for one transfer — where the money came from and
     * went to, when it was recorded and by whom, and the exact debit/credit it
     * posted to the ledger, so the whole movement is fully traceable.
     */
    public function show(JournalEntry $transfer): View
    {
        abort_if($transfer->reference_type !== 'Transfer', 404);

        $transfer->load('lines.account', 'creator', 'reverses', 'reversedBy');

        return view('shop.transfer.voucher', [
            'transfer' => $transfer,
            'fromAccount' => $this->fromAccount($transfer),
            'toAccount' => $this->toAccount($transfer),
        ]);
    }

    public function create(): View
    {
        $accounts = Account::whereIn('subtype', ['cash', 'bank', 'loan'])->orderBy('code')->get();

        return view('shop.transfer.create', [
            'accounts' => $accounts,
            // Only cash/bank sources have a spendable cap; loan accounts → null (no block).
            'accountBalances' => $accounts->mapWithKeys(fn ($a) => [
                $a->id => in_array($a->subtype, ['cash', 'bank'], true) ? $this->ledger->balance($a) : null,
            ]),
            // Outstanding balance per loan account, so repayment into it can be
            // capped on the form; null for non-loan destinations.
            'loanOutstanding' => $accounts->mapWithKeys(fn ($a) => [
                $a->id => $a->subtype === 'loan' ? $this->ledger->balance($a) : null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'exists:accounts,id'],
            'to_account_id' => ['required', 'exists:accounts,id', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->transfers->transfer(
                Account::where('id', $data['from_account_id'])->firstOrFail(),
                Account::where('id', $data['to_account_id'])->firstOrFail(),
                ['amount' => $data['amount'], 'date' => $data['date'], 'notes' => $data['notes'] ?? null],
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('transfers.index')->with('status', __('ui.common.saved'));
    }

    /** Source of a transfer: the credited line's account (money left here). */
    private function fromAccount(JournalEntry $entry): ?Account
    {
        return $entry->lines->first(fn ($l) => (float) $l->credit > 0)?->account;
    }

    /** Destination of a transfer: the debited line's account (money arrived here). */
    private function toAccount(JournalEntry $entry): ?Account
    {
        return $entry->lines->first(fn ($l) => (float) $l->debit > 0)?->account;
    }
}
