<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\Accounting\LedgerService;
use Modules\Accounting\Services\Reporting\ReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report export (FR-69) to CSV (opens in Excel) or PDF (via dompdf). Each
 * method turns a report into a title + header row + data rows and hands it to
 * the shared exporter, so new reports plug in with a few lines. report.view
 * gated at the route.
 */
class ExportController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private ReportService $reports,
    ) {}

    public function trialBalance(Request $request)
    {
        $data = $this->ledger->trialBalance();
        $rows = array_map(fn ($r) => [
            $r['code'], $r['name'], Money::taka($r['debit']), Money::taka($r['credit']),
        ], $data['rows']);

        return $this->export($request, __('ui.report.trial_balance'), 'trial-balance', [
            __('ui.report.code'), __('ui.report.account'), __('ui.report.debit'), __('ui.report.credit'),
        ], $rows);
    }

    public function stock(Request $request)
    {
        $data = $this->reports->stock();
        $rows = array_map(fn ($r) => [
            $r['name'], $r['unit'],
            rtrim(rtrim(number_format($r['qty'], 3), '0'), '.'),
            Money::taka($r['cost_price']), Money::taka($r['value']),
        ], $data['rows']);

        return $this->export($request, __('ui.report.stock'), 'stock', [
            __('ui.common.name'), __('ui.purchase.qty'), __('ui.report.reorder'),
            __('ui.report.value'), __('ui.report.total'),
        ], $rows);
    }

    public function accountStatement(Request $request, Account $account)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $data = $this->reports->accountStatement($account, $from, $to);

        // Opening row first, then every movement — mirrors the on-screen table.
        $rows = [[
            \Carbon\Carbon::parse($from)->format('d/m/Y'), '', __('ui.report.opening'),
            '', '', Money::taka($data['opening']),
        ]];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                \Carbon\Carbon::parse($r['date'])->format('d/m/Y'),
                $r['type_label'],
                $r['description'],
                $r['in'] > 0 ? Money::taka($r['in']) : '',
                $r['out'] > 0 ? Money::taka($r['out']) : '',
                Money::taka($r['balance']),
            ];
        }
        $rows[] = ['', '', __('ui.report.closing'), '', '', Money::taka($data['closing'])];

        $title = __('ui.report.account_statement').' — '.$account->code.' '.$account->name;

        return $this->export($request, $title, 'account-statement-'.$account->code, [
            __('ui.common.date'), __('ui.report.type'), __('ui.report.description'),
            __('ui.report.in'), __('ui.report.out'), __('ui.report.balance'),
        ], $rows);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function export(Request $request, string $title, string $slug, array $headers, array $rows): StreamedResponse|\Illuminate\Http\Response
    {
        return $request->input('format') === 'pdf'
            ? $this->pdf($title, $slug, $headers, $rows)
            : $this->csv($title, $slug, $headers, $rows);
    }

    private function csv(string $title, string $slug, array $headers, array $rows): StreamedResponse
    {
        $filename = "{$slug}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($title, $headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Bangla correctly.
            fputcsv($out, [$title]);
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function pdf(string $title, string $slug, array $headers, array $rows): \Illuminate\Http\Response
    {
        $pdf = Pdf::loadView('shop.export.table', [
            'title' => $title,
            'shop' => \App\Support\ShopProfile::name(),
            'headers' => $headers,
            'rows' => $rows,
        ]);

        return $pdf->download("{$slug}-".now()->format('Y-m-d').'.pdf');
    }
}
