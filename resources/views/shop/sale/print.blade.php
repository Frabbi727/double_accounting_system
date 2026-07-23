<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.invoice.title') }} {{ $sale->invoice_no ?? '#'.$sale->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', 'Noto Sans Bengali', system-ui, sans-serif;
            color: #111; margin: 0; padding: 24px; background: #f3f4f6;
            font-size: 14px;
        }
        .sheet {
            background: #fff; margin: 0 auto; padding: 28px;
            max-width: {{ $format === 'receipt' ? '320px' : '760px' }};
            box-shadow: 0 1px 4px rgba(0,0,0,.15);
        }
        h1 { font-size: 20px; margin: 0 0 2px; text-align: center; }
        .muted { color: #666; }
        .meta { display: flex; justify-content: space-between; margin: 14px 0; flex-wrap: wrap; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 6px 8px; text-align: left; }
        thead th { border-bottom: 2px solid #333; font-weight: 600; }
        tbody td { border-bottom: 1px solid #eee; }
        .num { text-align: right; }
        .totals { margin-top: 14px; margin-left: auto; width: {{ $format === 'receipt' ? '100%' : '280px' }}; }
        .totals .row { display: flex; justify-content: space-between; padding: 3px 0; }
        .totals .grand { border-top: 2px solid #333; font-weight: 700; font-size: 16px; padding-top: 6px; }
        .foot { text-align: center; margin-top: 22px; color: #444; }
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
        @if (\App\Support\ShopProfile::phone())
            <p class="muted" style="text-align:center; margin:0;">{{ __('ui.invoice.phone') }}: {{ \App\Support\ShopProfile::phone() }}</p>
        @endif
        <p class="muted" style="text-align:center; margin:6px 0 8px;">{{ __('ui.invoice.title') }}</p>

        <div class="meta">
            <div>
                <div><strong>{{ __('ui.invoice.invoice_no') }}:</strong> {{ $sale->invoice_no ?? '#'.$sale->id }}</div>
                <div><strong>{{ __('ui.common.date') }}:</strong> {{ $sale->date->format('d/m/Y') }}</div>
            </div>
            <div style="text-align:end;">
                <div><strong>{{ __('ui.invoice.customer') }}:</strong>
                    {{ $sale->customer?->name ?? __('ui.invoice.cash_customer') }}</div>
                @if ($sale->customer?->phone)
                    <div class="muted">{{ __('ui.invoice.phone') }}: {{ $sale->customer->phone }}</div>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('ui.invoice.product') }}</th>
                    <th class="num">{{ __('ui.invoice.qty') }}</th>
                    <th class="num">{{ __('ui.invoice.price') }}</th>
                    <th class="num">{{ __('ui.invoice.discount') }}</th>
                    <th class="num">{{ __('ui.invoice.line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($item->qty, 3), '0'), '.') }}</td>
                        <td class="num">@taka($item->unit_price)</td>
                        <td class="num">{{ (float) $item->discount > 0 ? \App\Support\Money::taka($item->discount) : '—' }}</td>
                        <td class="num">@taka($item->lineRevenue() - (float) $item->discount)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>{{ __('ui.invoice.gross') }}</span><span>@taka($sale->gross())</span></div>
            @if ((float) $sale->discount > 0)
                <div class="row"><span>{{ __('ui.invoice.bill_discount') }}</span><span>@taka($sale->discount)</span></div>
            @endif
            <div class="row grand"><span>{{ __('ui.invoice.net') }}</span><span>@taka($sale->net())</span></div>
            <div class="row"><span>{{ __('ui.invoice.paid') }}</span><span>@taka($sale->paid_amount)</span></div>
            @if ($sale->due() > 0.005)
                <div class="row"><span>{{ __('ui.invoice.due') }}</span><span>@taka($sale->due())</span></div>
            @endif
        </div>

        <p class="foot">{{ __('ui.invoice.thanks') }}</p>
    </div>

    <div class="actions">
        <a href="#" class="btn" onclick="window.print(); return false;">{{ __('ui.invoice.print') }}</a>
        @if ($format === 'receipt')
            <a href="{{ route('sales.print', $sale) }}" class="btn alt">A4</a>
        @else
            <a href="{{ route('sales.print', ['sale' => $sale, 'format' => 'receipt']) }}" class="btn alt">{{ __('ui.invoice.receipt') }}</a>
        @endif
    </div>
</body>
</html>
