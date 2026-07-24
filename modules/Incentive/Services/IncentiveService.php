<?php

namespace Modules\Incentive\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Modules\Finance\Services\AccountBalanceGuard;
use Modules\Incentive\Models\PartyIncentive;

/**
 * Incentives — bonuses tied to meeting a condition (FR-49, FR-50), now
 * attributed to a party and optionally settled against their due.
 *
 * Received (from a supplier) — it is our INCOME (credit 4030):
 *   settle cash → Debit Cash/Bank ;  settle due → Debit 2010 Payable
 * Given (to a customer) — it is our EXPENSE (debit 5100):
 *   settle cash → Credit Cash/Bank ;  settle due → Credit 1030 Receivable
 *
 * When settled against a due the entry touches the AR/AP control account and
 * carries reference_id = party id, so ReportService::partyControlLines() picks
 * it up and the remaining due stays fully ledger-derived. Every event is also
 * logged to party_incentives for the business record (type, basis, rate…).
 */
class IncentiveService
{
    private const EPSILON = 0.005;

    private const CASH_CODE = '1010';

    private const RECEIVABLE_CODE = '1030';

    private const PAYABLE_CODE = '2010';

    private const INCENTIVE_INCOME_CODE = '4030';

    private const INCENTIVE_EXPENSE_CODE = '5100';

    public function __construct(
        private LedgerService $ledger,
        private ReportService $reports,
        private AccountBalanceGuard $balanceGuard,
        private IncentiveBasisCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $data  direction (received|given), party_id,
     *   settle_mode (cash|due), account_id, basis, rate, amount, date, notes,
     *   ref_doc_type, ref_doc_id, period_from, period_to
     */
    public function record(array $data): PartyIncentive
    {
        $received = ($data['direction'] ?? 'received') === 'received';
        $partyType = $received ? 'supplier' : 'customer';
        $settleMode = $data['settle_mode'] ?? 'cash';
        $partyId = ! empty($data['party_id']) ? (int) $data['party_id'] : null;
        $date = $data['date'] ?? now()->toDateString();

        if ($settleMode === 'due' && ! $partyId) {
            throw new \InvalidArgumentException(__('incentive.errors.due_needs_party'));
        }

        // Resolve the money amount from its basis (fixed / percentage).
        ['base_amount' => $base, 'amount' => $amount] = $this->calculator->compute(
            ['party_type' => $partyType] + $data
        );

        return DB::transaction(function () use ($received, $partyType, $partyId, $settleMode, $amount, $base, $date, $data) {

            $cashOrBank = $this->cashOrBank($data);

            if ($settleMode === 'due') {
                $this->guardAgainstOverSettle($partyType, (int) $partyId, $amount);
            } elseif (! $received) {
                // Giving an incentive in cash sends money out — never overdraw.
                $this->balanceGuard->assertSufficient($cashOrBank, $amount, $date);
            }

            if ($received) {
                // Income: credit 4030; debit either payable (settle due) or cash.
                $debit = $settleMode === 'due'
                    ? $this->account(self::PAYABLE_CODE)
                    : $cashOrBank;

                $entry = $this->ledger->post(
                    date: $date,
                    referenceType: 'IncentiveIn',
                    referenceId: $partyId,
                    description: $data['notes'] ?? __('incentive.received_description'),
                    lines: [
                        ['account_id' => $debit->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $this->account(self::INCENTIVE_INCOME_CODE)->id, 'debit' => 0, 'credit' => $amount],
                    ],
                );
            } else {
                // Expense: debit 5100; credit either receivable (settle due) or cash.
                $credit = $settleMode === 'due'
                    ? $this->account(self::RECEIVABLE_CODE)
                    : $cashOrBank;

                $entry = $this->ledger->post(
                    date: $date,
                    referenceType: 'IncentiveOut',
                    referenceId: $partyId,
                    description: $data['notes'] ?? __('incentive.paid_description'),
                    lines: [
                        ['account_id' => $this->account(self::INCENTIVE_EXPENSE_CODE)->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $credit->id, 'debit' => 0, 'credit' => $amount],
                    ],
                );
            }

            return PartyIncentive::create([
                'kind' => 'incentive',
                'direction' => $received ? 'received' : 'given',
                'party_type' => $partyId ? $partyType : null,
                'party_id' => $partyId,
                'basis' => $data['basis'] ?? 'fixed',
                'rate' => ($data['basis'] ?? 'fixed') === 'fixed' ? null : round((float) ($data['rate'] ?? 0), 2),
                'base_amount' => $base,
                'amount' => $amount,
                'ref_doc_type' => $data['ref_doc_type'] ?? null,
                'ref_doc_id' => $data['ref_doc_id'] ?? null,
                'settle_mode' => $settleMode,
                'settle_account_id' => $settleMode === 'cash' ? $cashOrBank->id : null,
                'period_from' => $data['period_from'] ?? null,
                'period_to' => $data['period_to'] ?? null,
                'date' => $date,
                'notes' => $data['notes'] ?? null,
                'journal_entry_id' => $entry->id,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Backward-compatible shorthand: a plain cash incentive received from a
     * supplier (income). Kept for callers/tests predating the party flow.
     *
     * @param  array<string, mixed>  $data
     */
    public function receive(array $data): PartyIncentive
    {
        return $this->record(['direction' => 'received', 'settle_mode' => 'cash', 'basis' => 'fixed'] + $data);
    }

    /**
     * Backward-compatible shorthand: a plain cash incentive given out (expense).
     *
     * @param  array<string, mixed>  $data
     */
    public function pay(array $data): PartyIncentive
    {
        return $this->record(['direction' => 'given', 'settle_mode' => 'cash', 'basis' => 'fixed'] + $data);
    }

    /**
     * A due-settled incentive can never exceed what the party currently owes
     * (or is owed) — otherwise it would flip the control account into an
     * advance balance.
     *
     * @param  'customer'|'supplier'  $partyType
     */
    private function guardAgainstOverSettle(string $partyType, int $id, float $amount): void
    {
        $due = $this->reports->partyDue($partyType, $id);

        if ($amount > $due + self::EPSILON) {
            throw new \InvalidArgumentException(__('incentive.errors.exceeds_due', [
                'due' => Money::taka(max($due, 0)),
            ]));
        }
    }

    private function cashOrBank(array $data): Account
    {
        if (! empty($data['account_id'])) {
            return Account::findOrFail($data['account_id']);
        }

        return $this->account(self::CASH_CODE);
    }

    private function account(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
