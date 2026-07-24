<?php

namespace Modules\Accounting\Services\Master;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Accounting\OpeningEntryService;

/**
 * Creates cash / bank / loan accounts with their opening balance
 * entered on the same form.
 */
class AccountService
{
    public function __construct(
        private OpeningEntryService $opening,
        private LedgerService $ledger,
    ) {}

    public function create(array $data): Account
    {
        return DB::transaction(function () use ($data) {

            // A user-created account is named once, in whichever language the
            // shopkeeper typed. Store it in both columns so the locale-aware
            // accessor always resolves a value; explicit name_bn/name_en win.
            $name = $data['name'] ?? null;

            $account = Account::create([
                'code' => $data['code'] ?? $this->nextCode($data['subtype']),
                'name_bn' => $data['name_bn'] ?? $name,
                'name_en' => $data['name_en'] ?? $name,
                'type' => $data['type'],
                'subtype' => $data['subtype'],
                'is_system' => false,
            ]);

            $amount = (float) ($data['opening_balance'] ?? 0);

            if ($amount > 0) {
                $this->opening->post(
                    account: $account,
                    amount: $amount,
                    date: config('shop.cutoff_date'),
                    source: $account,
                );
            }

            return $account->fresh();
        });
    }

    public function correctOpening(Account $account, float $newAmount, string $reason): void
    {
        $this->opening->correct(
            source: $account,
            account: $account,
            newAmount: $newAmount,
            date: config('shop.cutoff_date'),
            reason: $reason,
        );
    }

    /**
     * Set or edit an account's opening balance from the account screen.
     *
     * Works whether or not an opening balance already exists, and always keeps
     * a full audit trail — corrections reverse the old entry and post a new one,
     * never editing in place. Zeroing an existing opening simply reverses it.
     */
    public function setOpening(Account $account, float $amount, string $reason): void
    {
        DB::transaction(function () use ($account, $amount, $reason) {

            $existing = $this->opening->findOpeningEntry($account);
            $amount = round($amount, 2);

            // No opening yet — post a clean first entry (skip the "corrected:" label).
            if ($existing === null) {
                if ($amount > 0) {
                    $this->opening->post(
                        account: $account,
                        amount: $amount,
                        date: config('shop.cutoff_date'),
                        source: $account,
                    );
                }

                return; // amount 0 with nothing to change — nothing to do.
            }

            // An opening exists. Zeroing it means removing it entirely.
            if ($amount <= 0) {
                $this->ledger->reverse($existing, $reason);

                return;
            }

            // Otherwise reverse the old entry and repost the corrected amount.
            $this->correctOpening($account, $amount, $reason);
        });
    }

    /** Next free code in the right series: cash 1010+, bank 1021+, loan 2020+. */
    private function nextCode(string $subtype): string
    {
        $base = match ($subtype) {
            'cash' => 1010,
            'bank' => 1021,
            'loan' => 2020,
            default => 9000,
        };

        $used = Account::where('code', '>=', (string) $base)
            ->where('code', '<', (string) ($base + 100))
            ->pluck('code')
            ->map(fn ($c) => (int) $c)
            ->all();

        $code = $base;
        while (in_array($code, $used, true)) {
            $code++;
        }

        return (string) $code;
    }
}
