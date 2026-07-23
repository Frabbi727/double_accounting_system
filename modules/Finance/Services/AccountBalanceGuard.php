<?php

namespace Modules\Finance\Services;

use App\Support\Money;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;

/**
 * Blocks spending more money than a cash/bank account actually holds.
 *
 * Money leaves an account through a supplier payment, an expense or a transfer.
 * Each of those services asks this guard, just before posting, whether the
 * source account can cover the amount. The balance is read live from the ledger
 * (never a stored column) via LedgerService::balance().
 *
 * Only money-holding asset accounts (subtype cash/bank) are guarded. A loan
 * account is a liability that can legitimately be drawn further negative, so it
 * is exempt.
 */
class AccountBalanceGuard
{
    /** Money is compared at 2 decimal places, matching the rest of Finance. */
    private const EPSILON = 0.005;

    public function __construct(
        private LedgerService $ledger,
    ) {}

    public function assertSufficient(Account $account, float $amount, ?string $asOf = null): void
    {
        if (! in_array($account->subtype, ['cash', 'bank'], true)) {
            return;
        }

        $balance = $this->ledger->balance($account, $asOf);

        if ($amount > $balance + self::EPSILON) {
            throw new \InvalidArgumentException(__('finance.errors.insufficient_balance', [
                'account' => $account->name,
                'balance' => Money::taka(max($balance, 0)),
            ]));
        }
    }
}
