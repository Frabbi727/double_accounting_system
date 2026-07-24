{{-- Standalone printable audit voucher for one transfer (a journal entry with
     reference_type Transfer). Expects: $transfer (JournalEntry with lines.account,
     creator, reverses, reversedBy), $fromAccount (?Account), $toAccount (?Account). --}}
@php
    $amount = $transfer->totalDebit();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.transfer.voucher') }} #{{ $transfer->id }}</title>
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
        .flow { text-align: center; font-size: 16px; font-weight: 600; margin: 4px 0; }
        .flow .arrow { color: #6b7280; margin: 0 8px; }
        .amount-box {
            background: #f9fafb; border: 1px solid #eee; border-radius: 6px;
            padding: 8px 10px; margin-top: 6px; text-align: center; font-size: 18px; font-weight: 700;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 6px 8px; text-align: left; }
        thead th { border-bottom: 2px solid #333; font-weight: 600; font-size: 12px; }
        tbody td { border-bottom: 1px solid #eee; }
        tfoot td { border-top: 2px solid #333; font-weight: 700; }
        .num { text-align: right; }
        .actions { text-align: center; margin: 16px 0; }
        .btn { background:#1f2937; color:#fff; border:0; border-radius:6px; padding:8px 18px; font-size:14px; cursor:pointer; text-decoration:none; }
        .btn.alt { background:#6b7280; margin-left:6px; }
        .badge { display:inline-block; font-size:12px; padding:2px 8px; border-radius:10px; }
        .badge.reversed { background:#fee2e2; color:#b91c1c; }
        .badge.reversal { background:#fef3c7; color:#b45309; }

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
            {{ __('ui.transfer.voucher') }}
            <span class="num">#{{ $transfer->id }}</span>
        </p>

        {{-- কখন / কে / অবস্থা --}}
        <section>
            <h2>{{ __('ui.transfer.details') }}</h2>
            <div class="rows">
                <div class="row"><span class="label">{{ __('ui.common.date') }}</span><span class="val">{{ $transfer->date->format('d/m/Y') }}</span></div>
                @if ($transfer->created_at)
                    <div class="row"><span class="label">{{ __('ui.transfer.entered_on') }}</span><span class="val">{{ $transfer->created_at->format('d/m/Y h:i A') }}</span></div>
                @endif
                @if ($transfer->creator)
                    <div class="row"><span class="label">{{ __('ui.transfer.entered_by') }}</span><span class="val">{{ $transfer->creator->name }}</span></div>
                @endif
                @if ($transfer->isReversed())
                    <div class="row"><span class="label">{{ __('ui.transfer.status') }}</span><span class="val"><span class="badge reversed">{{ __('ui.report.audit_reversed') }}</span></span></div>
                @elseif ($transfer->isReversal())
                    <div class="row"><span class="label">{{ __('ui.transfer.status') }}</span><span class="val"><span class="badge reversal">{{ __('ui.report.audit_reversal') }}</span></span></div>
                @endif
            </div>
        </section>

        {{-- কোথা থেকে কোথায় --}}
        <section>
            <h2>{{ __('ui.transfer.movement') }}</h2>
            <div class="flow">
                <span>{{ $fromAccount?->name ?? '—' }}</span>
                <span class="arrow">→</span>
                <span>{{ $toAccount?->name ?? '—' }}</span>
            </div>
            <div class="rows">
                @if ($fromAccount)
                    <div class="row"><span class="label">{{ __('ui.transfer.from') }}</span><span class="val">{{ $fromAccount->code }} — {{ $fromAccount->name }}</span></div>
                @endif
                @if ($toAccount)
                    <div class="row"><span class="label">{{ __('ui.transfer.to') }}</span><span class="val">{{ $toAccount->code }} — {{ $toAccount->name }}</span></div>
                @endif
            </div>
            <div class="amount-box">@taka($amount)</div>
        </section>

        {{-- ledger-এ ঠিক কী বসলো (double-entry) — কীভাবে --}}
        <section>
            <h2>{{ __('ui.transfer.ledger_effect') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('ui.account.title') }}</th>
                        <th class="num">{{ __('ui.transfer.debit') }}</th>
                        <th class="num">{{ __('ui.transfer.credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transfer->lines as $line)
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
                        <td class="num">@taka($transfer->lines->sum('debit'))</td>
                        <td class="num">@taka($transfer->lines->sum('credit'))</td>
                    </tr>
                </tfoot>
            </table>
        </section>

        @if ($transfer->description)
            <section>
                <h2>{{ __('ui.transfer.note') }}</h2>
                <p style="margin:0;">{{ $transfer->description }}</p>
            </section>
        @endif
    </div>

    <div class="actions">
        <a href="#" class="btn" onclick="window.print(); return false;">{{ __('ui.transfer.print') }}</a>
        <a href="{{ route('transfers.index') }}" class="btn alt">{{ __('ui.transfer.back') }}</a>
    </div>
</body>
</html>
