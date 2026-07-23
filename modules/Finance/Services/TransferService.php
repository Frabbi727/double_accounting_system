<?php

namespace Modules\Finance\Services;

use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\Accounting\LedgerService;

/**
 * Moves money between two of the shop's own accounts (e.g. cash → bank).
 *
 *   Debit   destination account
 *   Credit  source account
 */
class TransferService
{
    public function __construct(
        private LedgerService $ledger,
        private AccountBalanceGuard $balanceGuard,
    ) {}

    /**
     * @param  array{amount:float, date?:string, notes?:string}  $data
     */
    public function transfer(Account $from, Account $to, array $data): JournalEntry
    {
        if ($from->id === $to->id) {
            throw new \InvalidArgumentException(__('finance.errors.same_account'));
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException(__('finance.errors.amount_positive'));
        }

        $date = $data['date'] ?? now()->toDateString();
        $this->balanceGuard->assertSufficient($from, $amount, $date);

        return $this->ledger->post(
            date: $date,
            referenceType: 'Transfer',
            referenceId: null,
            description: $data['notes'] ?? __('finance.transfer_description', [
                'from' => $from->name,
                'to' => $to->name,
            ]),
            lines: [
                ['account_id' => $to->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $from->id, 'debit' => 0, 'credit' => $amount],
            ],
        );
    }
}
