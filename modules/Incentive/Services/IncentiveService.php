<?php

namespace Modules\Incentive\Services;

use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\LedgerService;

/**
 * Incentives — bonuses tied to meeting a condition (FR-49, FR-50).
 *
 * Received (from a supplier): it is our INCOME.
 *   Debit   Cash/Bank
 *   Credit  4030 Incentive Income
 *
 * Paid (commission to a staff member or customer): it is our EXPENSE.
 *   Debit   5100 Incentive Expense
 *   Credit  Cash/Bank
 *
 * A single ledger entry captures each — no separate table needed yet.
 */
class IncentiveService
{
    private const CASH_CODE = '1010';

    private const INCENTIVE_INCOME_CODE = '4030';

    private const INCENTIVE_EXPENSE_CODE = '5100';

    public function __construct(
        private LedgerService $ledger,
    ) {}

    /**
     * @param  array{amount:float, date?:string, account_id?:int, notes?:string}  $data
     */
    public function receive(array $data): JournalEntry
    {
        $amount = $this->amount($data);
        $account = $this->cashOrBank($data);
        $date = $data['date'] ?? now()->toDateString();

        return $this->ledger->post(
            date: $date,
            referenceType: 'IncentiveIn',
            referenceId: null,
            description: $data['notes'] ?? __('incentive.received_description'),
            lines: [
                ['account_id' => $account->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $this->account(self::INCENTIVE_INCOME_CODE)->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    /**
     * @param  array{amount:float, date?:string, account_id?:int, notes?:string}  $data
     */
    public function pay(array $data): JournalEntry
    {
        $amount = $this->amount($data);
        $account = $this->cashOrBank($data);
        $date = $data['date'] ?? now()->toDateString();

        return $this->ledger->post(
            date: $date,
            referenceType: 'IncentiveOut',
            referenceId: null,
            description: $data['notes'] ?? __('incentive.paid_description'),
            lines: [
                ['account_id' => $this->account(self::INCENTIVE_EXPENSE_CODE)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $account->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    private function amount(array $data): float
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('incentive.errors.amount_positive'));
        }

        return $amount;
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
