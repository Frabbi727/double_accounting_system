<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Supplier;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;

/**
 * Reports are permission-gated at the route (report.view). Cost/profit columns
 * are additionally gated on cost.view inside the views, so a salesperson who
 * somehow reaches a report never sees cost or profit (NFR-07).
 */
class ReportController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private ReportService $reports,
    ) {}

    public function index()
    {
        return view('shop.report.index');
    }

    public function trialBalance()
    {
        return view('shop.report.trial_balance', $this->ledger->trialBalance());
    }

    public function balanceSheet()
    {
        return view('shop.report.balance_sheet', $this->reports->balanceSheet());
    }

    public function dayBook(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        return view('shop.report.day_book', [
            'date'    => $date,
            'entries' => $this->reports->dayBook($date),
        ]);
    }

    public function aging(Request $request)
    {
        $party = $request->input('party') === 'supplier' ? 'supplier' : 'customer';

        return view('shop.report.aging', [
            'party'  => $party,
            'report' => $this->reports->aging($party),
        ]);
    }

    public function cashBook(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return view('shop.report.cash_book', [
            'from'   => $from,
            'to'     => $to,
            'report' => $this->reports->cashBook('1010', $from, $to),
        ]);
    }

    public function lowStock()
    {
        $rows = collect($this->reports->stock()['rows'])
            ->filter(fn ($r) => $r['low_stock'])
            ->values();

        return view('shop.report.low_stock', ['rows' => $rows]);
    }

    public function productProfit(Request $request)
    {
        // Product profit reveals cost — double-guard beyond report.view.
        abort_unless($request->user()->can('cost.view'), 403);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return view('shop.report.product_profit', [
            'from'   => $from,
            'to'     => $to,
            'report' => $this->reports->productProfit($from, $to),
        ]);
    }

    public function partyStatement(Request $request)
    {
        $party = $request->input('party') === 'supplier' ? 'supplier' : 'customer';
        $parties = ($party === 'supplier' ? Supplier::query() : Customer::query())
            ->orderBy('name')->get();

        $id = (int) $request->input('id');
        $statement = $id ? $this->reports->partyStatement($party, $id) : null;

        return view('shop.report.party_statement', [
            'party'      => $party,
            'parties'    => $parties,
            'selectedId' => $id,
            'statement'  => $statement,
        ]);
    }

    public function auditLog(Request $request)
    {
        $type = $request->input('type');
        $from = $request->input('from');
        $to = $request->input('to');

        $entries = JournalEntry::with(['creator', 'lines'])
            ->when($type, fn ($q) => $q->where('reference_type', $type))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->latest('created_at')->latest('id')
            ->limit(200)->get();

        return view('shop.report.audit_log', [
            'entries' => $entries,
            'types'   => JournalEntry::query()->distinct()->orderBy('reference_type')->pluck('reference_type'),
            'type'    => $type,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function stock()
    {
        return view('shop.report.stock', $this->reports->stock());
    }

    public function customerDue()
    {
        return view('shop.report.party_due', [
            'title' => __('ui.report.customer_due'),
            'party' => 'customer',
            'direction' => 'received',
            'rows' => $this->reports->partyDues('customer'),
        ]);
    }

    public function supplierDue()
    {
        return view('shop.report.party_due', [
            'title' => __('ui.report.supplier_due'),
            'party' => 'supplier',
            'direction' => 'made',
            'rows' => $this->reports->partyDues('supplier'),
        ]);
    }

    public function profitLoss()
    {
        return view('shop.report.profit_loss', $this->reports->profitAndLoss());
    }
}
