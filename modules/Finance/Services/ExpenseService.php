<?php

namespace Modules\Finance\Services;

use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\LedgerService;

/**
 * Records a shop expense (rent, salary, electricity, ...).
 *
 *   Debit   5xxx Expense account
 *   Credit  Cash/Bank
 *
 * A single ledger entry captures the whole thing — no separate table needed.
 */
class ExpenseService
{
    private const CASH_CODE = '1010';

    public function __construct(
        private LedgerService $ledger,
    ) {}

    /**
     * Expected $data shape:
     *   expense_account_id | expense_code, amount, date?, payment_account_id?, notes?
     */
    public function create(array $data): JournalEntry
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('finance.errors.amount_positive'));
        }

        $expense = $this->resolveExpenseAccount($data);

        if ($expense->type !== AccountType::Expense) {
            throw new \InvalidArgumentException(__('finance.errors.not_expense_account'));
        }

        $payment = $this->paymentAccount($data);
        $date = $data['date'] ?? now()->toDateString();

        return $this->ledger->post(
            date: $date,
            referenceType: 'Expense',
            referenceId: $expense->id,
            description: $data['notes'] ?? __('finance.expense_description', ['account' => $expense->name]),
            lines: [
                ['account_id' => $expense->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $payment->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }

    private function resolveExpenseAccount(array $data): Account
    {
        if (! empty($data['expense_account_id'])) {
            return Account::findOrFail($data['expense_account_id']);
        }

        return Account::where('code', $data['expense_code'])->firstOrFail();
    }

    private function paymentAccount(array $data): Account
    {
        if (! empty($data['payment_account_id'])) {
            return Account::findOrFail($data['payment_account_id']);
        }

        return Account::where('code', self::CASH_CODE)->firstOrFail();
    }
}
