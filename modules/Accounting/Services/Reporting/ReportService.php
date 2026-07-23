<?php

namespace Modules\Accounting\Services\Reporting;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Product;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;

/**
 * Read-only financial reports. Every figure here is DERIVED from the ledger,
 * stock movements or subsidiary balances — nothing is read from a cached
 * column, so the reports can never disagree with the books.
 *
 * The trial balance itself lives on LedgerService::trialBalance(); this class
 * builds the statements on top of it.
 */
class ReportService
{
    private const EPSILON = 0.005;

    public function __construct(
        private LedgerService $ledger,
    ) {}

    /**
     * Balance Sheet as of a date: Assets = Liabilities + Equity.
     *
     * Equity includes the current-period net profit (retained earnings are
     * only crystallised at year-end), so the sheet always balances.
     *
     * @return array{
     *   assets: array, liabilities: array, equity: array,
     *   total_assets: float, total_liabilities: float, total_equity: float,
     *   net_profit: float, balanced: bool
     * }
     */
    public function balanceSheet(?string $asOf = null): array
    {
        $assets = $this->sideRows(AccountType::Asset, $asOf);
        $liabilities = $this->sideRows(AccountType::Liability, $asOf);
        $equity = $this->sideRows(AccountType::Equity, $asOf);

        $netProfit = $this->profitAndLoss($asOf)['net_profit'];

        $totalAssets = $this->sumRows($assets);
        $totalLiabilities = $this->sumRows($liabilities);
        $totalEquity = $this->sumRows($equity);

        // Current-period profit belongs to the owner until it is closed out.
        $equity[] = [
            'code' => null,
            'name' => __('accounting.reports.current_profit'),
            'balance' => round($netProfit, 2),
        ];
        $totalEquity = round($totalEquity + $netProfit, 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => $totalEquity,
            'net_profit' => round($netProfit, 2),
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < self::EPSILON,
        ];
    }

    /**
     * Profit & Loss for a period (income − expenses).
     *
     * @return array{
     *   income: array, expenses: array,
     *   total_income: float, total_expense: float, net_profit: float
     * }
     */
    public function profitAndLoss(?string $asOf = null, ?string $from = null): array
    {
        $income = $this->sideRows(AccountType::Income, $asOf, $from);
        $expenses = $this->sideRows(AccountType::Expense, $asOf, $from);

        $totalIncome = $this->sumRows($income);
        $totalExpense = $this->sumRows($expenses);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'total_income' => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'net_profit' => round($totalIncome - $totalExpense, 2),
        ];
    }

    /**
     * Day book: every journal entry on a given date with its lines.
     *
     * @return array<int, array{id:int, date:string, reference_type:string, description:string, lines:array, total:float}>
     */
    public function dayBook(string $date): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereDate('e.date', $date)
            ->orderBy('e.id')
            ->select([
                'e.id as entry_id', 'e.reference_type', 'e.description',
                'a.code as account_code', 'a.name_bn', 'a.name_en',
                'l.debit', 'l.credit', 'l.memo',
            ])
            ->get();

        $entries = [];
        foreach ($rows as $r) {
            $entries[$r->entry_id] ??= [
                'id' => $r->entry_id,
                'date' => $date,
                'reference_type' => $r->reference_type,
                'description' => $r->description,
                'lines' => [],
                'total' => 0.0,
            ];

            $entries[$r->entry_id]['lines'][] = [
                'account_code' => $r->account_code,
                'account_name' => $this->localizedName($r->name_bn, $r->name_en),
                'debit' => round((float) $r->debit, 2),
                'credit' => round((float) $r->credit, 2),
                'memo' => $r->memo,
            ];
            $entries[$r->entry_id]['total'] = round($entries[$r->entry_id]['total'] + (float) $r->debit, 2);
        }

        return array_values($entries);
    }

    /**
     * Stock report: every active product with its current quantity and value.
     *
     * @return array{rows: array, total_value: float}
     */
    public function stock(?string $asOf = null): array
    {
        $rows = [];
        $total = 0.0;

        foreach (Product::where('is_active', true)->orderBy('name')->get() as $product) {
            $qty = $product->currentStock($asOf);

            if (abs($qty) < 0.0005 && ! $product->movements()->exists()) {
                continue;
            }

            $value = $product->stockValue($asOf);
            $total += $value;

            $rows[] = [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'qty' => round($qty, 3),
                'cost_price' => round((float) $product->cost_price, 4),
                'value' => round($value, 2),
                'low_stock' => $product->isLowStock(),
            ];
        }

        return ['rows' => $rows, 'total_value' => round($total, 2)];
    }

    /**
     * Aging of receivables (customers) or payables (suppliers), bucketed by
     * how long the opening debt has been outstanding.
     *
     * Note: this ages the OPENING party balances (the only party detail the
     * system currently records at line level). Once invoiced sales/purchases
     * carry due dates, this method extends to them.
     *
     * @param  'customer'|'supplier'  $party
     * @return array{rows: array, buckets: array, total: float}
     */
    public function aging(string $party = 'customer', ?string $asOf = null): array
    {
        $model = $party === 'supplier' ? Supplier::class : Customer::class;

        $bucketLabels = ['0-30', '31-60', '61-90', '90+'];
        $buckets = array_fill_keys($bucketLabels, 0.0);
        $rows = [];
        $total = 0.0;

        foreach ($model::all() as $record) {
            $open = $record->openingBalances()->whereNull('reversed_at')->get();
            if ($open->isEmpty()) {
                continue;
            }

            $perRow = array_fill_keys($bucketLabels, 0.0);
            $rowTotal = 0.0;

            foreach ($open as $item) {
                $age = $item->ageInDays($asOf);
                $bucket = $this->agingBucket($age);
                $amount = (float) $item->amount;

                $perRow[$bucket] += $amount;
                $buckets[$bucket] += $amount;
                $rowTotal += $amount;
            }

            $total += $rowTotal;
            $rows[] = [
                'id' => $record->id,
                'name' => $record->name,
                'buckets' => array_map(fn ($v) => round($v, 2), $perRow),
                'total' => round($rowTotal, 2),
            ];
        }

        return [
            'rows' => $rows,
            'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
            'total' => round($total, 2),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * Rows for one account type, each at its natural-direction balance.
     * Accounts with no activity are skipped.
     */
    private function sideRows(AccountType $type, ?string $asOf, ?string $from = null): array
    {
        $rows = [];

        $accounts = Account::where('type', $type->value)->orderBy('code')->get();

        foreach ($accounts as $account) {
            $balance = $from !== null
                ? $this->periodBalance($account, $from, $asOf)
                : $this->ledger->balance($account, $asOf);

            if (abs($balance) < self::EPSILON) {
                continue;
            }

            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'balance' => round($balance, 2),
            ];
        }

        return $rows;
    }

    /** Natural-direction balance restricted to a date range (for P&L periods). */
    private function periodBalance(Account $account, string $from, ?string $to): float
    {
        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account->id)
            ->whereDate('e.date', '>=', $from);

        if ($to !== null) {
            $query->whereDate('e.date', '<=', $to);
        }

        $totals = $query->selectRaw('COALESCE(SUM(l.debit),0) as d, COALESCE(SUM(l.credit),0) as c')->first();

        $debit = (float) $totals->d;
        $credit = (float) $totals->c;

        return $account->type->increasesWithDebit()
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    private function sumRows(array $rows): float
    {
        return round(array_sum(array_column($rows, 'balance')), 2);
    }

    private function agingBucket(int $age): string
    {
        return match (true) {
            $age <= 30 => '0-30',
            $age <= 60 => '31-60',
            $age <= 90 => '61-90',
            default => '90+',
        };
    }

    private function localizedName(?string $bn, ?string $en): string
    {
        $primary = app()->getLocale() === 'en' ? $en : $bn;

        return $primary ?? $en ?? $bn ?? '';
    }
}
