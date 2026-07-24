<?php

namespace Modules\Accounting\Services\Accounting;

use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Reporting\ReportService;

/**
 * Read-only "final review" of everything that will be frozen when the opening
 * period is locked. Every figure is DERIVED from the ledger, stock movements
 * and subsidiary balances — nothing is stored — so this can be called any
 * number of times before the lock without side effects.
 *
 * The controller and the pre-lock guard both consume build(), so the review
 * screen and the actual lock always agree on what is (and isn't) present.
 */
class OpeningSummaryService
{
    private const EPSILON = 0.005;

    public function __construct(
        private ReportService $reports,
        private LedgerService $ledger,
    ) {}

    /**
     * The complete opening picture: categorized balances, subsidiary dues,
     * inventory, the cash/bank accounts, headline totals and the validation
     * warnings that help the owner spot missing data before locking.
     *
     * @return array{
     *   sections: array{assets: array, liabilities: array, equity: array},
     *   customers: array{total: float, count: int},
     *   suppliers: array{total: float, count: int},
     *   inventory: array{total_value: float, product_count: int, without_stock: int},
     *   accounts: array{rows: array, count: int},
     *   totals: array{total_assets: float, total_liabilities: float, total_equity: float, net_profit: float, balanced: bool, opening_cash: float},
     *   warnings: array<int, array{key: string, severity: string}>,
     *   has_blocker: bool
     * }
     */
    public function build(): array
    {
        $bs = $this->reports->balanceSheet();

        $sections = [
            'assets' => $this->accountsBySubtype(AccountType::Asset),
            'liabilities' => $this->accountsBySubtype(AccountType::Liability),
            'equity' => $this->accountsBySubtype(AccountType::Equity),
        ];

        // Cash/bank accounts and the opening cash position they sum to.
        $cashRows = [];
        $openingCash = 0.0;
        foreach (Account::cashOrBank()->orderBy('code')->get() as $account) {
            $balance = round($this->ledger->balance($account), 2);
            $cashRows[] = ['code' => $account->code, 'name' => $account->name, 'subtype' => $account->subtype, 'balance' => $balance];
            $openingCash += $balance;
        }

        $customerDues = $this->reports->partyDues('customer');
        $supplierDues = $this->reports->partyDues('supplier');

        $stock = $this->reports->stock();
        $activeProducts = Product::where('is_active', true)->get();
        $withoutStock = $activeProducts->filter(fn (Product $p) => $p->openingMovement() === null)->count();

        $totals = [
            'total_assets' => $bs['total_assets'],
            'total_liabilities' => $bs['total_liabilities'],
            'total_equity' => $bs['total_equity'],
            'net_profit' => $bs['net_profit'],
            'balanced' => $bs['balanced'],
            'opening_cash' => round($openingCash, 2),
        ];

        return [
            'sections' => $sections,
            'customers' => ['total' => $this->sumDues($customerDues), 'count' => count($customerDues)],
            'suppliers' => ['total' => $this->sumDues($supplierDues), 'count' => count($supplierDues)],
            'inventory' => [
                'total_value' => $stock['total_value'],
                'product_count' => $activeProducts->count(),
                'without_stock' => $withoutStock,
            ],
            'accounts' => ['rows' => $cashRows, 'count' => count($cashRows)],
            'totals' => $totals,
            'warnings' => $this->warnings($totals, $cashRows, $withoutStock, count($customerDues), count($supplierDues)),
            'has_blocker' => ! $totals['balanced'],
        ];
    }

    /**
     * Non-zero accounts of one type, grouped by subtype (cash, bank, inventory,
     * payable, loan, capital, other) so the view can show them under headings.
     *
     * @return array<string, array<int, array{code: string, name: string, balance: float}>>
     */
    private function accountsBySubtype(AccountType $type): array
    {
        $groups = [];

        foreach (Account::where('type', $type->value)->orderBy('code')->get() as $account) {
            $balance = round($this->ledger->balance($account), 2);

            if (abs($balance) < self::EPSILON) {
                continue;
            }

            $groups[$account->subtype][] = [
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $balance,
            ];
        }

        return $groups;
    }

    /**
     * Soft warnings to help the owner review; only "not balanced" (checked in
     * build()) is a hard blocker. Each entry is {key, severity} — the view
     * localizes the key.
     *
     * @param  array<int, array<string, mixed>>  $cashRows
     * @return array<int, array{key: string, severity: string}>
     */
    private function warnings(array $totals, array $cashRows, int $productsWithoutStock, int $customerCount, int $supplierCount): array
    {
        $warnings = [];

        if (! $totals['balanced']) {
            $warnings[] = ['key' => 'not_balanced', 'severity' => 'blocker'];
        }

        if (count($cashRows) === 0) {
            $warnings[] = ['key' => 'no_account', 'severity' => 'warning'];
        }

        if (abs($totals['opening_cash']) < self::EPSILON) {
            $warnings[] = ['key' => 'no_cash', 'severity' => 'warning'];
        }

        if ($productsWithoutStock > 0) {
            $warnings[] = ['key' => 'product_no_stock', 'severity' => 'warning'];
        }

        // Master exists but nobody carries an opening balance.
        if (Customer::count() > 0 && $customerCount === 0) {
            $warnings[] = ['key' => 'customer_no_due', 'severity' => 'warning'];
        }

        if (Supplier::count() > 0 && $supplierCount === 0) {
            $warnings[] = ['key' => 'supplier_no_balance', 'severity' => 'warning'];
        }

        return $warnings;
    }

    /** @param array<int, array{due: float}> $dues */
    private function sumDues(array $dues): float
    {
        return round(array_sum(array_column($dues, 'due')), 2);
    }
}
