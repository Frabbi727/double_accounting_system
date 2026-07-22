<?php

namespace Modules\Accounting\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Exceptions\PeriodLockedException;
use Modules\Accounting\Exceptions\UnbalancedJournalException;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;

/**
 * The single gateway for writing to the ledger.
 *
 * NOTHING else in the application may insert into journal_entries or
 * journal_entry_lines. Every module posts through this service.
 *
 * This class deliberately knows nothing about sales, purchases, products
 * or parties. It only knows accounts, debits and credits.
 */
class LedgerService
{
    /** Money is compared at 2 decimal places. */
    private const EPSILON = 0.005;

    public function __construct(
        private PeriodLockService $periodLock,
    ) {}

    /**
     * Post a balanced journal entry.
     *
     * @param  array<int, array{account_id:int, debit?:float, credit?:float, memo?:string}>  $lines
     *
     * @throws UnbalancedJournalException if debits !== credits
     * @throws PeriodLockedException if the date falls in a locked period
     */
    public function post(
        string $date,
        string $referenceType,
        ?int $referenceId,
        string $description,
        array $lines,
    ): JournalEntry {

        if ($this->periodLock->isLocked($date)) {
            throw PeriodLockedException::forDate($date);
        }

        $this->assertBalanced($lines);
        $this->assertNoZeroLines($lines);

        return DB::transaction(function () use ($date, $referenceType, $referenceId, $description, $lines) {

            $entry = JournalEntry::create([
                'date' => $date,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => round($line['debit'] ?? 0, 2),
                    'credit' => round($line['credit'] ?? 0, 2),
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Reverse an existing entry by posting its mirror image.
     *
     * The original entry is never modified or deleted — this is what keeps
     * the audit trail intact.
     */
    public function reverse(JournalEntry $original, string $reason): JournalEntry
    {
        if ($original->reversed_by_id !== null) {
            throw new \RuntimeException(__('accounting.errors.already_reversed'));
        }

        $mirroredLines = $original->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'debit' => $line->credit,   // swapped
            'credit' => $line->debit,    // swapped
            'memo' => $line->memo,
        ])->all();

        return DB::transaction(function () use ($original, $mirroredLines, $reason) {

            $reversal = $this->post(
                date: $original->date->toDateString(),
                referenceType: $original->reference_type,
                referenceId: $original->reference_id,
                description: __('accounting.reversal_prefix').": {$original->description} — {$reason}",
                lines: $mirroredLines,
            );

            // Link both directions so the chain is traceable.
            $reversal->update(['reverses_id' => $original->id]);
            $original->update(['reversed_by_id' => $reversal->id]);

            return $reversal;
        });
    }

    /**
     * Current balance of an account, expressed as a positive number in the
     * account's natural direction.
     *
     * Asset/Expense  -> debits increase it
     * Liability/Equity/Income -> credits increase it
     *
     * Reversed entries net themselves out automatically because the reversal
     * lines are ordinary lines with swapped sides.
     */
    public function balance(Account $account, ?string $asOf = null): float
    {
        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account->id);

        if ($asOf !== null) {
            $query->whereDate('e.date', '<=', $asOf);
        }

        $totals = $query->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')
            ->first();

        $debit = (float) $totals->d;
        $credit = (float) $totals->c;

        return $account->type->increasesWithDebit()
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    /**
     * Trial balance: every account with its debit/credit column.
     *
     * @return array{rows: array, total_debit: float, total_credit: float, balanced: bool}
     */
    public function trialBalance(?string $asOf = null): array
    {
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach (Account::orderBy('code')->get() as $account) {
            $balance = $this->balance($account, $asOf);

            if (abs($balance) < self::EPSILON) {
                continue;   // skip accounts with no activity
            }

            $onDebitSide = $account->type->increasesWithDebit();

            // A negative balance flips the account to the other column.
            $debit = $onDebitSide ? max($balance, 0) : max(-$balance, 0);
            $credit = $onDebitSide ? max(-$balance, 0) : max($balance, 0);

            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->value,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];
        }

        return [
            'rows' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'balanced' => abs($totalDebit - $totalCredit) < self::EPSILON,
        ];
    }

    /** Throws unless the whole ledger balances. Call this in tests and after migration. */
    public function assertLedgerBalanced(): void
    {
        $tb = $this->trialBalance();

        if (! $tb['balanced']) {
            throw UnbalancedJournalException::make($tb['total_debit'], $tb['total_credit']);
        }
    }

    // ------------------------------------------------------------------
    // guards
    // ------------------------------------------------------------------

    private function assertBalanced(array $lines): void
    {
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($debit - $credit) >= self::EPSILON) {
            throw UnbalancedJournalException::make($debit, $credit);
        }

        if ($debit < self::EPSILON) {
            throw new \InvalidArgumentException(__('accounting.errors.zero_entry'));
        }
    }

    /** A line must be either a debit or a credit, never both, never neither. */
    private function assertNoZeroLines(array $lines): void
    {
        foreach ($lines as $i => $line) {
            $d = round($line['debit'] ?? 0, 2);
            $c = round($line['credit'] ?? 0, 2);

            if ($d > 0 && $c > 0) {
                throw new \InvalidArgumentException(__('accounting.errors.line_both_sides', ['line' => $i]));
            }
            if ($d <= 0 && $c <= 0) {
                throw new \InvalidArgumentException(__('accounting.errors.line_no_side', ['line' => $i]));
            }
            if ($d < 0 || $c < 0) {
                throw new \InvalidArgumentException(__('accounting.errors.line_negative', ['line' => $i]));
            }
        }
    }
}
