{{-- Standalone printable detail voucher for one incentive OR rebate.
     Expects: $incentive (PartyIncentive, with journalEntry.lines.account,
     settleAccount, creator[, product]), $remainingDue (?float). --}}
@php
    $basisKey = [
        'fixed' => 'ui.incentive.basis_fixed',
        'pct_of_due' => 'ui.incentive.basis_pct_due',
        'pct_of_invoice' => 'ui.incentive.basis_pct_invoice',
        'pct_of_product_value' => 'ui.incentive.basis_pct_product',
        'pct_of_sales' => 'ui.incentive.basis_pct_sales',
    ];
    $isRebate = $incentive->kind === 'rebate';
    $party = $incentive->party();
    $indexRoute = $isRebate ? route('rebates.index') : route('incentives.index');
    $rate = rtrim(rtrim(number_format((float) $incentive->rate, 2), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.incentive.kind_'.$incentive->kind) }} {{ __('ui.incentive.voucher') }} #{{ $incentive->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', 'Noto Sans Bengali', system-ui, sans-serif;
            color: #111; margin: 0; padding: 24px; background: #f3f4f6;
            font-size: 14px;
        }
        .sheet {
            background: #fff; margin: 0 auto; padding: 28px;
            max-width: 640px;
            box-shadow: 0 1px 4px rgba(0,0,0,.15);
        }
        h1 { font-size: 20px; margin: 0 0 2px; text-align: center; }
        .muted { color: #666; }
        .doc-title { text-align: center; margin: 6px 0 14px; font-weight: 600; }
        .doc-title .num { color: #666; font-weight: 400; }
        section { margin: 16px 0; }
        section > h2 {
            font-size: 12px; text-transform: uppercase; letter-spacing: .04em;
            color: #6b7280; margin: 0 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px;
        }
        .rows .row { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; }
        .rows .row .label { color: #555; }
        .rows .row .val { text-align: right; font-weight: 500; }
        .formula {
            background: #f9fafb; border: 1px solid #eee; border-radius: 6px;
            padding: 8px 10px; margin-top: 6px; text-align: center; font-size: 15px;
        }
        .formula .eq { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 6px 8px; text-align: left; }
        thead th { border-bottom: 2px solid #333; font-weight: 600; font-size: 12px; }
        tbody td { border-bottom: 1px solid #eee; }
        tfoot td { border-top: 2px solid #333; font-weight: 700; }
        .num { text-align: right; }
        .actions { text-align: center; margin: 16px 0; }
        .btn { background:#1f2937; color:#fff; border:0; border-radius:6px; padding:8px 18px; font-size:14px; cursor:pointer; text-decoration:none; }
        .btn.alt { background:#6b7280; margin-left:6px; }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; padding: 0; }
            .actions { display: none; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        @if (\App\Support\ShopProfile::logoUrl())
            <div style="text-align:center; margin-bottom:6px;"><img src="{{ \App\Support\ShopProfile::logoUrl() }}" alt="" style="max-height:64px; width:auto;"></div>
        @endif
        <h1>{{ \App\Support\ShopProfile::name() }}</h1>
        @if (\App\Support\ShopProfile::address())
            <p class="muted" style="text-align:center; margin:0;">{{ \App\Support\ShopProfile::address() }}</p>
        @endif

        <p class="doc-title">
            {{ __('ui.incentive.kind_'.$incentive->kind) }} {{ __('ui.incentive.voucher') }}
            <span class="num">#{{ $incentive->id }}</span>
        </p>

        {{-- কে / কখন / কী ধরন --}}
        <section>
            <h2>{{ __('ui.incentive.details') }}</h2>
            <div class="rows">
                <div class="row"><span class="label">{{ __('ui.common.date') }}</span><span class="val">{{ $incentive->date->format('d/m/Y') }}</span></div>
                @unless ($isRebate)
                    <div class="row"><span class="label">{{ __('ui.incentive.direction') }}</span><span class="val">{{ __('ui.incentive.'.$incentive->direction) }}</span></div>
                @endunless
                @if ($party)
                    <div class="row"><span class="label">{{ __('ui.incentive.party') }}</span><span class="val">{{ $party->name }}@if ($party->phone) <span class="muted">({{ $party->phone }})</span>@endif</span></div>
                @endif
                @if ($incentive->creator)
                    <div class="row"><span class="label">{{ __('ui.incentive.created_by') }}</span><span class="val">{{ $incentive->creator->name }}</span></div>
                @endif
            </div>
        </section>

        {{-- কীভাবে হিসাব হলো --}}
        <section>
            <h2>{{ __('ui.incentive.computed_as') }}</h2>
            <div class="rows">
                <div class="row"><span class="label">{{ __('ui.incentive.basis') }}</span><span class="val">{{ __($basisKey[$incentive->basis] ?? 'ui.incentive.basis_fixed') }}</span></div>
                @if ($isRebate && $incentive->product)
                    <div class="row"><span class="label">{{ __('ui.rebate.product') }}</span><span class="val">{{ $incentive->product->name }}</span></div>
                    <div class="row"><span class="label">{{ __('ui.incentive.stock_value') }}</span><span class="val">@taka(round($incentive->product->currentStock() * (float) $incentive->product->cost_price, 2))</span></div>
                @endif
                @if ($incentive->ref_doc_id)
                    <div class="row"><span class="label">{{ __('ui.incentive.invoice') }}</span><span class="val">{{ __('ui.ref_type.'.$incentive->ref_doc_type) }} #{{ $incentive->ref_doc_id }}</span></div>
                @endif
                @if ($incentive->period_from && $incentive->period_to)
                    <div class="row"><span class="label">{{ __('ui.incentive.period_from') }} – {{ __('ui.incentive.period_to') }}</span><span class="val">{{ $incentive->period_from->format('d/m/Y') }} – {{ $incentive->period_to->format('d/m/Y') }}</span></div>
                @endif
            </div>

            @if ($incentive->basis === 'fixed')
                <div class="formula">@taka($incentive->amount)</div>
            @else
                <div class="formula">
                    {{ $rate }}% <span class="muted">×</span> @taka($incentive->base_amount)
                    <span class="eq">=</span> @taka($incentive->amount)
                </div>
            @endif
        </section>

        {{-- কীভাবে সমন্বয় হলো + বর্তমান বাকি --}}
        <section>
            <h2>{{ __('ui.incentive.settle') }}</h2>
            <div class="rows">
                <div class="row"><span class="label">{{ __('ui.incentive.settle_mode') }}</span><span class="val">{{ __('ui.incentive.settle_'.$incentive->settle_mode) }}</span></div>
                @if ($incentive->settle_mode === 'cash' && $incentive->settleAccount)
                    <div class="row"><span class="label">{{ __('ui.incentive.account') }}</span><span class="val">{{ $incentive->settleAccount->name }}</span></div>
                @elseif ($incentive->settle_mode === 'due')
                    <div class="row"><span class="label">{{ __('ui.incentive.reduces_due') }}</span><span class="val">@taka($incentive->amount)</span></div>
                @endif
                @if (! is_null($remainingDue))
                    <div class="row"><span class="label">{{ __('ui.incentive.remaining_due') }}</span><span class="val">@taka(max($remainingDue, 0))</span></div>
                @endif
            </div>
        </section>

        {{-- ledger-এ ঠিক কী বসলো (double-entry) --}}
        @if ($incentive->journalEntry)
            <section>
                <h2>{{ __('ui.incentive.ledger_effect') }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('ui.account.title') }}</th>
                            <th class="num">{{ __('ui.incentive.debit') }}</th>
                            <th class="num">{{ __('ui.incentive.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incentive->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                                <td class="num">{{ (float) $line->debit > 0 ? \App\Support\Money::taka($line->debit) : '—' }}</td>
                                <td class="num">{{ (float) $line->credit > 0 ? \App\Support\Money::taka($line->credit) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>{{ __('ui.report.total') }}</td>
                            <td class="num">@taka($incentive->journalEntry->lines->sum('debit'))</td>
                            <td class="num">@taka($incentive->journalEntry->lines->sum('credit'))</td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        @endif

        @if ($incentive->notes)
            <section>
                <h2>{{ __('ui.incentive.note') }}</h2>
                <p style="margin:0;">{{ $incentive->notes }}</p>
            </section>
        @endif
    </div>

    <div class="actions">
        <a href="#" class="btn" onclick="window.print(); return false;">{{ __('ui.incentive.print') }}</a>
        <a href="{{ $indexRoute }}" class="btn alt">{{ __('ui.incentive.back') }}</a>
    </div>
</body>
</html>
