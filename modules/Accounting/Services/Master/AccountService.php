<?php

namespace Modules\Accounting\Services\Master;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\OpeningEntryService;

/**
 * Creates cash / bank / loan accounts with their opening balance
 * entered on the same form.
 */
class AccountService
{
    public function __construct(
        private OpeningEntryService $opening,
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
