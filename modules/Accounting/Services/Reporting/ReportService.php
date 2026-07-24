<?php

namespace Modules\Accounting\Services\Reporting;

use Carbon\Carbon;
use Illuminate\Support\Collection;
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
     * how long each outstanding charge has been unpaid.
     *
     * Fully ledger-derived: a party's charges are their opening balance rows
     * (dated by original_date) plus every invoice/bill that raised the control
     * account (Sale/Purchase), while receipts/payments and returns form a
     * payment pool applied FIFO — oldest charge first. Each surviving charge is
     * bucketed by its own age, so the subsidiary total always equals the party's
     * slice of the receivable (1030) / payable (2010) control account.
     *
     * @param  'customer'|'supplier'  $party
     * @return array{rows: array, buckets: array, total: float}
     */
    public function aging(string $party = 'customer', ?string $asOf = null): array
    {
        $isCustomer = $party !== 'supplier';
        $model = $isCustomer ? Customer::class : Supplier::class;
        $asOfDate = $asOf ? Carbon::parse($asOf) : now();

        $bucketLabels = ['0-30', '31-60', '61-90', '90+'];
        $buckets = array_fill_keys($bucketLabels, 0.0);
        $rows = [];
        $total = 0.0;

        foreach ($model::all() as $record) {
            // Dated charges (things that raise what is owed), oldest first.
            $charges = [];

            foreach ($record->openingBalances()->whereNull('reversed_at')->get() as $item) {
                $charges[] = ['date' => $item->original_date->toDateString(), 'amount' => (float) $item->amount];
            }

            // Payment pool (receipts/payments and returns) reduces the oldest debt.
            $payments = 0.0;

            foreach ($this->partyControlLines($isCustomer, $record->id) as $line) {
                // Customer control (AR): a debit raises the debt, a credit clears it.
                // Supplier control (AP): reversed.
                $net = $isCustomer
                    ? (float) $line->debit - (float) $line->credit
                    : (float) $line->credit - (float) $line->debit;

                if ($net > 0) {
                    $charges[] = ['date' => $line->date, 'amount' => $net];
                } else {
                    $payments += -$net;
                }
            }

            usort($charges, fn ($a, $b) => $a['date'] <=> $b['date']);

            // Apply the payment pool FIFO against the oldest charges.
            foreach ($charges as &$charge) {
                if ($payments <= 0) {
                    break;
                }
                $applied = min($payments, $charge['amount']);
                $charge['amount'] -= $applied;
                $payments -= $applied;
            }
            unset($charge);

            $perRow = array_fill_keys($bucketLabels, 0.0);
            $rowTotal = 0.0;

            foreach ($charges as $charge) {
                if ($charge['amount'] <= 0) {
                    continue;
                }
                $age = (int) Carbon::parse($charge['date'])->diffInDays($asOfDate);
                $bucket = $this->agingBucket($age);

                $perRow[$bucket] += $charge['amount'];
                $buckets[$bucket] += $charge['amount'];
                $rowTotal += $charge['amount'];
            }

            if (abs($rowTotal) < self::EPSILON) {
                continue;
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

    /**
     * Cash book (or any cash/bank account) for a date range: opening balance,
     * every movement with a running balance, and the closing balance.
     *
     * @return array{account: Account, opening: float, rows: array, closing: float}
     */
    public function cashBook(string $accountCode = '1010', ?string $from = null, ?string $to = null): array
    {
        $account = Account::where('code', $accountCode)->firstOrFail();

        // Opening = balance up to the day before `from` (null → 0).
        $opening = $from !== null
            ? $this->ledger->balance($account, Carbon::parse($from)->subDay()->toDateString())
            : 0.0;

        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account->id);

        if ($from !== null) {
            $query->whereDate('e.date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('e.date', '<=', $to);
        }

        $lines = $query->orderBy('e.date')->orderBy('e.id')
            ->select(['e.date', 'e.description', 'l.debit', 'l.credit'])
            ->get();

        $running = $opening;
        $rows = [];
        foreach ($lines as $line) {
            // Cash/bank is an asset: debit increases (in), credit decreases (out).
            $running += (float) $line->debit - (float) $line->credit;
            $rows[] = [
                'date'        => $line->date,
                'description' => $line->description,
                'in'          => round((float) $line->debit, 2),
                'out'         => round((float) $line->credit, 2),
                'balance'     => round($running, 2),
            ];
        }

        return [
            'account' => $account,
            'opening' => round($opening, 2),
            'rows'    => $rows,
            'closing' => round($running, 2),
        ];
    }

    /**
     * Per-product profit for a date range, from the frozen sale-line costs
     * (docs rule: historical profit never shifts). Requires cost visibility.
     *
     * @return array{rows: array, total_revenue: float, total_cogs: float, total_profit: float}
     */
    public function productProfit(?string $from = null, ?string $to = null): array
    {
        $query = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id');

        if ($from !== null) {
            $query->whereDate('s.date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('s.date', '<=', $to);
        }

        $grouped = $query->selectRaw('
                p.id as product_id,
                p.name as name,
                COALESCE(SUM(si.qty),0) as qty,
                COALESCE(SUM(si.qty * si.unit_price - si.discount),0) as revenue,
                COALESCE(SUM(si.qty * si.cost_price),0) as cogs
            ')
            ->groupBy('p.id', 'p.name')
            ->orderBy('p.name')
            ->get();

        $rows = [];
        $totalRevenue = 0.0;
        $totalCogs = 0.0;

        foreach ($grouped as $g) {
            $revenue = round((float) $g->revenue, 2);
            $cogs = round((float) $g->cogs, 2);
            $totalRevenue += $revenue;
            $totalCogs += $cogs;

            $rows[] = [
                'name'    => $g->name,
                'qty'     => round((float) $g->qty, 3),
                'revenue' => $revenue,
                'cogs'    => $cogs,
                'profit'  => round($revenue - $cogs, 2),
            ];
        }

        return [
            'rows'          => $rows,
            'total_revenue' => round($totalRevenue, 2),
            'total_cogs'    => round($totalCogs, 2),
            'total_profit'  => round($totalRevenue - $totalCogs, 2),
        ];
    }

    /**
     * Statement of account for one customer or supplier: the opening balance
     * followed by every ledger movement that touches their control account
     * (receivable 1030 / payable 2010), in date order, with a running balance.
     *
     * It is built straight from the ledger — invoices/bills (Sale/Purchase),
     * returns (SaleReturn/PurchaseReturn) and receipts/payments (PaymentIn/
     * PaymentOut) — so the closing balance always equals the party's slice of
     * the control account. `charge` raises what is owed, `payment` lowers it;
     * for a customer that is "owed to us", for a supplier "owed by us".
     *
     * Note: a sale/purchase settled in full on the same day posts no control
     * line, so it does not appear here — it never changed the outstanding due.
     *
     * @param  'customer'|'supplier'  $party
     * @return array{party:string, record:Customer|Supplier, opening:float, rows:array, total_charge:float, total_payment:float, closing:float}
     */
    public function partyStatement(string $party, int $id): array
    {
        $isCustomer = $party !== 'supplier';

        /** @var Customer|Supplier $record */
        $record = ($isCustomer ? Customer::class : Supplier::class)::findOrFail($id);

        $lines = $this->partyControlLines($isCustomer, $id);

        $opening = round($record->openingBalance(), 2);
        $running = $opening;
        $totalCharge = 0.0;
        $totalPayment = 0.0;
        $rows = [];

        foreach ($lines as $line) {
            // Customer control (AR, asset): a debit raises what they owe us.
            // Supplier control (AP, liability): a credit raises what we owe them.
            $charge = $isCustomer ? (float) $line->debit : (float) $line->credit;
            $payment = $isCustomer ? (float) $line->credit : (float) $line->debit;

            $running += $charge - $payment;
            $totalCharge += $charge;
            $totalPayment += $payment;

            $rows[] = [
                'date' => $line->date,
                'description' => $line->description,
                'reference_type' => $line->reference_type,
                'reference_id' => $line->reference_id,
                'charge' => round($charge, 2),
                'payment' => round($payment, 2),
                'balance' => round($running, 2),
            ];
        }

        return [
            'party' => $isCustomer ? 'customer' : 'supplier',
            'record' => $record,
            'opening' => $opening,
            'rows' => $rows,
            'total_charge' => round($totalCharge, 2),
            'total_payment' => round($totalPayment, 2),
            'closing' => round($running, 2),
        ];
    }

    /**
     * Every customer (or supplier) with a non-zero outstanding balance, each at
     * its true ledger due (opening + control-account movement), name-sorted.
     * Powers the "customer due / supplier due" settlement list.
     *
     * @param  'customer'|'supplier'  $party
     * @return array<int, array{id:int, name:string, due:float}>
     */
    public function partyDues(string $party): array
    {
        $isCustomer = $party !== 'supplier';
        $model = $isCustomer ? Customer::class : Supplier::class;
        $rows = [];

        foreach ($model::orderBy('name')->get() as $record) {
            $due = $this->recordDue($isCustomer, $record);

            if (abs($due) > self::EPSILON) {
                $rows[] = ['id' => $record->id, 'name' => $record->name, 'due' => $due];
            }
        }

        return $rows;
    }

    /**
     * The current outstanding due for a single party — the authoritative cap a
     * receipt/payment may not exceed. 0 when the party has no balance.
     *
     * @param  'customer'|'supplier'  $party
     */
    public function partyDue(string $party, int $id): float
    {
        $isCustomer = $party !== 'supplier';
        $record = ($isCustomer ? Customer::class : Supplier::class)::find($id);

        return $record ? $this->recordDue($isCustomer, $record) : 0.0;
    }

    /** Opening balance plus every control-account movement for one party. */
    private function recordDue(bool $isCustomer, Customer|Supplier $record): float
    {
        $due = $record->openingBalance();

        foreach ($this->partyControlLines($isCustomer, $record->id) as $line) {
            $due += $isCustomer
                ? (float) $line->debit - (float) $line->credit
                : (float) $line->credit - (float) $line->debit;
        }

        return round($due, 2);
    }

    /**
     * Every ledger line that touches a party's control account (receivable
     * 1030 / payable 2010): their invoices/bills (Sale/Purchase), returns
     * (SaleReturn/PurchaseReturn) and receipts/payments (PaymentIn/PaymentOut),
     * in date order. Excludes the Opening entry — callers seed that separately.
     *
     * @return Collection<int, object{date:string, description:string, debit:string, credit:string}>
     */
    private function partyControlLines(bool $isCustomer, int $id): Collection
    {
        $controlId = Account::where('code', $isCustomer ? '1030' : '2010')->value('id');

        // Documents (sales/purchases) whose ledger entries reference this party.
        $docIds = DB::table($isCustomer ? 'sales' : 'purchases')
            ->where($isCustomer ? 'customer_id' : 'supplier_id', $id)
            ->pluck('id')->all();

        $docTypes = $isCustomer ? ['Sale', 'SaleReturn'] : ['Purchase', 'PurchaseReturn'];

        // Unpaid (credit-mode) asset acquisitions credit AP 2010, keyed to the
        // supplier via assets.supplier_id — same document pattern as Purchase.
        $assetIds = $isCustomer ? [] : DB::table('assets')
            ->where('supplier_id', $id)
            ->where('payment_mode', 'credit')
            ->pluck('id')->all();

        // Entries keyed directly to the party id (not a document): receipts and
        // payments, plus any incentive/rebate settled against the party's due
        // (IncentiveOut lowers a customer's receivable; IncentiveIn/RebatePayable
        // lower a supplier's payable). All carry reference_id = party id.
        $partyKeyedTypes = $isCustomer
            ? ['PaymentIn', 'IncentiveOut']
            : ['PaymentOut', 'IncentiveIn', 'RebatePayable'];

        return DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $controlId)
            ->where(function ($q) use ($docTypes, $docIds, $partyKeyedTypes, $id, $assetIds) {
                $q->where(function ($w) use ($docTypes, $docIds) {
                    $w->whereIn('e.reference_type', $docTypes);
                    empty($docIds) ? $w->whereRaw('1 = 0') : $w->whereIn('e.reference_id', $docIds);
                })->orWhere(function ($w) use ($partyKeyedTypes, $id) {
                    $w->whereIn('e.reference_type', $partyKeyedTypes)->where('e.reference_id', $id);
                })->orWhere(function ($w) use ($assetIds) {
                    // Credit-mode asset purchases (keyed to asset id, not a party id).
                    $w->where('e.reference_type', 'AssetPurchase');
                    empty($assetIds) ? $w->whereRaw('1 = 0') : $w->whereIn('e.reference_id', $assetIds);
                });
            })
            ->orderBy('e.date')->orderBy('e.id')
            ->select(['e.date', 'e.description', 'e.reference_type', 'e.reference_id', 'l.debit', 'l.credit'])
            ->get();
    }

    /**
     * The full billed value of one sale/purchase document, read straight from
     * its ledger entry: a sale's gross revenue (4010 credit) or a purchase's
     * capitalized inventory value (1040 debit) — independent of how much was
     * paid at the time. Used as a percentage base for invoice-level incentives.
     */
    public function documentTotal(string $docType, int $docId): float
    {
        $isSale = $docType === 'Sale';
        $accountId = Account::where('code', $isSale ? '4010' : '1040')->value('id');

        $row = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.reference_type', $docType)
            ->where('e.reference_id', $docId)
            ->where('l.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit')
            ->first();

        return round($isSale ? (float) $row->credit : (float) $row->debit, 2);
    }

    /**
     * Every posted sale/purchase grouped by party, as
     * `[party_id => [ ['id'=>, 'label'=>, 'total'=>], ... ]]`. Feeds the
     * invoice-basis picker (pct_of_invoice) — document totals come straight from
     * the ledger in one grouped query, so there is no N+1.
     *
     * @param  'customer'|'supplier'  $party
     * @return array<int, array<int, array{id:int, label:string, total:float}>>
     */
    public function partyDocuments(string $party): array
    {
        $isCustomer = $party !== 'supplier';
        $table = $isCustomer ? 'sales' : 'purchases';
        $fk = $isCustomer ? 'customer_id' : 'supplier_id';
        $docType = $isCustomer ? 'Sale' : 'Purchase';
        $col = $isCustomer ? 'credit' : 'debit';
        $accountId = Account::where('code', $isCustomer ? '4010' : '1040')->value('id');

        $rows = DB::table($table.' as d')
            ->join('journal_entries as e', function ($j) use ($docType) {
                $j->on('e.reference_id', '=', 'd.id')->where('e.reference_type', $docType);
            })
            ->join('journal_entry_lines as l', function ($j) use ($accountId) {
                $j->on('l.journal_entry_id', '=', 'e.id')->where('l.account_id', $accountId);
            })
            ->whereNotNull('d.'.$fk)
            ->groupBy('d.'.$fk, 'd.id', 'd.invoice_no', 'd.date')
            ->orderBy('d.date', 'desc')->orderBy('d.id', 'desc')
            ->selectRaw("d.$fk as party_id, d.id, d.invoice_no, d.date, SUM(l.$col) as total")
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->party_id][] = [
                'id' => (int) $r->id,
                'label' => ($r->invoice_no ?: '#'.$r->id).' · '.$r->date,
                'total' => round((float) $r->total, 2),
            ];
        }

        return $map;
    }

    /**
     * Total business done with a party over a window — a customer's gross sales
     * or a supplier's gross purchases, summed from the documents' ledger
     * entries. Drives the "sell %" incentive basis.
     *
     * @param  'customer'|'supplier'  $party
     */
    public function partyTurnover(string $party, int $id, string $from, string $to): float
    {
        $isCustomer = $party !== 'supplier';
        $accountId = Account::where('code', $isCustomer ? '4010' : '1040')->value('id');

        $docIds = DB::table($isCustomer ? 'sales' : 'purchases')
            ->where($isCustomer ? 'customer_id' : 'supplier_id', $id)
            ->pluck('id')->all();

        if (empty($docIds)) {
            return 0.0;
        }

        $row = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.reference_type', $isCustomer ? 'Sale' : 'Purchase')
            ->whereIn('e.reference_id', $docIds)
            ->whereBetween('e.date', [$from, $to])
            ->where('l.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(l.debit), 0) as debit, COALESCE(SUM(l.credit), 0) as credit')
            ->first();

        return round($isCustomer ? (float) $row->credit : (float) $row->debit, 2);
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

    /**
     * Full activity statement for ONE account: opening balance followed by
     * every ledger movement that touched it, in date order, with a running
     * balance — so the owner can see how money entered, where it went, to whom
     * and how much. Works for any account (cash, bank, loan, control, income,
     * expense), direction-aware so "in" always means "made the balance grow".
     *
     * Each row carries a human-readable `type` label (Sale, Purchase, Payment
     * to supplier, Expense, Transfer, Rebate, Incentive, …) derived from the
     * journal entry's reference_type, plus the entry's own description (which
     * already names the counterparty). Built straight from the immutable ledger,
     * so `closing` always equals LedgerService::balance() for the same range.
     *
     * @return array{account:Account, opening:float, rows:array, closing:float, total_in:float, total_out:float}
     */
    public function accountStatement(Account $account, ?string $from = null, ?string $to = null): array
    {
        $opening = $from !== null
            ? $this->ledger->balance($account, Carbon::parse($from)->subDay()->toDateString())
            : 0.0;

        $query = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $account->id);

        if ($from !== null) {
            $query->whereDate('e.date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('e.date', '<=', $to);
        }

        $lines = $query->orderBy('e.date')->orderBy('e.id')
            ->select(['e.date', 'e.id as entry_id', 'e.reference_type', 'e.reference_id', 'e.description', 'l.debit', 'l.credit'])
            ->get();

        // For an asset/expense account a debit grows the balance; for a
        // liability/equity/income account a credit does. "in" = growth.
        $debitIsIn = $account->type->increasesWithDebit();

        $running = $opening;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $rows = [];

        foreach ($lines as $line) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            $in = $debitIsIn ? $debit : $credit;
            $out = $debitIsIn ? $credit : $debit;

            $running += $in - $out;
            $totalIn += $in;
            $totalOut += $out;

            $rows[] = [
                'date'           => $line->date,
                'entry_id'       => $line->entry_id,
                'reference_type' => $line->reference_type,
                'type_label'     => $this->refTypeLabel($line->reference_type),
                'description'    => $line->description,
                'in'             => round($in, 2),
                'out'            => round($out, 2),
                'balance'        => round($running, 2),
            ];
        }

        return [
            'account'   => $account,
            'opening'   => round($opening, 2),
            'rows'      => $rows,
            'closing'   => round($running, 2),
            'total_in'  => round($totalIn, 2),
            'total_out' => round($totalOut, 2),
        ];
    }

    /**
     * Localized, human-readable label for a ledger reference_type
     * (Sale, PaymentOut, Rebate, IncentiveIn, …). Falls back to the raw type
     * for anything not yet translated, so nothing ever renders blank.
     */
    public function refTypeLabel(string $type): string
    {
        $key = "ui.ref_type.$type";
        $label = __($key);

        return $label === $key ? $type : $label;
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
