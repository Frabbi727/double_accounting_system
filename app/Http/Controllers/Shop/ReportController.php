<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Modules\Accounting\Models\Customer;
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

    public function trialBalance()
    {
        return view('shop.report.trial_balance', $this->ledger->trialBalance());
    }

    public function stock()
    {
        return view('shop.report.stock', $this->reports->stock());
    }

    public function customerDue()
    {
        $rows = Customer::orderBy('name')->get()
            ->map(fn (Customer $c) => ['name' => $c->name, 'due' => $c->openingBalance()])
            ->filter(fn ($r) => abs($r['due']) > 0.005)
            ->values();

        return view('shop.report.party_due', [
            'title' => __('ui.report.customer_due'),
            'rows' => $rows,
        ]);
    }

    public function supplierDue()
    {
        $rows = Supplier::orderBy('name')->get()
            ->map(fn (Supplier $s) => ['name' => $s->name, 'due' => $s->openingBalance()])
            ->filter(fn ($r) => abs($r['due']) > 0.005)
            ->values();

        return view('shop.report.party_due', [
            'title' => __('ui.report.supplier_due'),
            'rows' => $rows,
        ]);
    }

    public function profitLoss()
    {
        return view('shop.report.profit_loss', $this->reports->profitAndLoss());
    }
}
