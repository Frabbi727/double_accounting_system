<?php

namespace Modules\Accounting\Services\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Exceptions\OpeningAlreadyPostedException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;

/**
 * Posts opening balances for ANY master record — customer, supplier,
 * product or cash/bank account — through one code path.
 *
 * The contra side is always Owner's Equity (3010). That is what makes the
 * ledger balance after every single opening entry, so the user can add
 * records one at a time in any order and the trial balance never breaks.
 *
 * The caller does NOT decide debit vs credit. This service reads the
 * account type and works it out.
 */
class OpeningEntryService
{
    public const EQUITY_CODE = '3010';

    public const AR_CODE = '1030';

    public const AP_CODE = '2010';

    public const INVENTORY_CODE = '1040';

    public function __construct(
        private LedgerService $ledger,
    ) {}

    /**
     * @param  Account  $account  the real-world account (AR, AP, Inventory, Cash, Bank, Loan)
     * @param  float  $amount  always a positive number
     * @param  Model  $source  the master record this opening belongs to
     * @param  bool  $allowMultiple  true for parties (a customer/supplier may
     *                               carry several old unpaid invoices); false for single-valued
     *                               sources (an account balance, a product's opening stock),
     *                               where a second opening is a mistake and is rejected.
     */
    public function post(
        Account $account,
        float $amount,
        string $date,
        Model $source,
        ?string $reference = null,
        bool $allowMultiple = false,
    ): JournalEntry {

        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('accounting.errors.opening_positive'));
        }

        if (! $allowMultiple) {
            $this->assertNotAlreadyPosted($source);
        }

        $equity = $this->equityAccount();
        $amount = round($amount, 2);

        // Asset/Expense accounts are debited to increase.
        // Liability/Equity/Income accounts are credited to increase.
        // The equity side always takes the opposite.
        $lines = $account->type->increasesWithDebit()
            ? [
                ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $equity->id,  'debit' => 0,       'credit' => $amount],
            ]
            : [
                ['account_id' => $equity->id,  'debit' => $amount, 'credit' => 0],
                ['account_id' => $account->id, 'debit' => 0,       'credit' => $amount],
            ];

        return $this->ledger->post(
            date: $date,
            referenceType: 'Opening',
            referenceId: $source->getKey(),
            description: $this->describe($source, $reference),
            lines: $lines,
        );
    }

    /**
     * Correct an already-posted opening balance.
     * Reverses the original entry, then posts the corrected one.
     * Never edits in place.
     */
    public function correct(
        Model $source,
        Account $account,
        float $newAmount,
        string $date,
        string $reason,
    ): JournalEntry {

        return DB::transaction(function () use ($source, $account, $newAmount, $date, $reason) {

            $original = $this->findOpeningEntry($source);

            if ($original) {
                $this->ledger->reverse($original, $reason);
            }

            // assertNotAlreadyPosted() passes now because the original is reversed.
            return $this->post($account, $newAmount, $date, $source, __('accounting.corrected').": {$reason}");
        });
    }

    /** The live opening (non-reversed) entry for a master record, if any. */
    public function findOpeningEntry(Model $source): ?JournalEntry
    {
        return JournalEntry::where('reference_type', 'Opening')
            ->where('reference_id', $source->getKey())
            ->whereNull('reversed_by_id')
            ->whereNull('reverses_id')       // exclude reversal entries themselves
            ->where('description', 'like', class_basename($source).'%')
            ->first();
    }

    public function equityAccount(): Account
    {
        return Account::where('code', self::EQUITY_CODE)->firstOrFail();
    }

    public function receivableAccount(): Account
    {
        return Account::where('code', self::AR_CODE)->firstOrFail();
    }

    public function payableAccount(): Account
    {
        return Account::where('code', self::AP_CODE)->firstOrFail();
    }

    public function inventoryAccount(): Account
    {
        return Account::where('code', self::INVENTORY_CODE)->firstOrFail();
    }

    // ------------------------------------------------------------------

    private function assertNotAlreadyPosted(Model $source): void
    {
        if ($this->findOpeningEntry($source) !== null) {
            throw OpeningAlreadyPostedException::for(class_basename($source), $source->getKey());
        }
    }

    /**
     * The description ALWAYS begins with the source's class basename
     * (e.g. "Customer:") because findOpeningEntry() matches on it with a
     * LIKE. Only the human-readable suffix is localized.
     */
    private function describe(Model $source, ?string $reference): string
    {
        $label = class_basename($source);
        $name = $source->name ?? "#{$source->getKey()}";
        $ref = $reference ? " ({$reference})" : '';

        return "{$label}: {$name} — ".__('accounting.opening_balance').$ref;
    }
}
