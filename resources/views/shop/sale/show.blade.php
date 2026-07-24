<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">
                {{ __('ui.sale.details') }} — {{ $sale->invoice_no ?? '#'.$sale->id }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('sales.print', $sale) }}" target="_blank" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">
                    {{ __('ui.invoice.print') }}
                </a>
                <a href="{{ route('sales.index') }}" class="text-gray-500 px-3 py-1.5 text-sm">
                    {{ __('ui.sale.back_to_list') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')

        {{-- Main Document Metadata Grid --}}
        <div class="bg-white rounded-lg shadow p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">{{ __('ui.common.date') }}</p>
                <p class="font-medium mt-0.5">{{ $sale->date->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.sale.invoice_no') }}</p>
                <p class="font-medium mt-0.5">{{ $sale->invoice_no ?? '#'.$sale->id }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.invoice.customer') }}</p>
                <p class="font-medium mt-0.5">
                    @if($sale->customer)
                        <a href="{{ route('customers.show', $sale->customer) }}" class="text-blue-600 hover:underline">
                            {{ $sale->customer->name }}
                        </a>
                        @if($sale->customer->phone)
                            <span class="block text-xs text-gray-400 font-normal mt-0.5">{{ $sale->customer->phone }}</span>
                        @endif
                    @else
                        {{ __('ui.sale.walk_in') }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.sale.sold_by') }}</p>
                <p class="font-medium mt-0.5">{{ $sale->creator?->name ?? '—' }}</p>
            </div>
        </div>

        {{-- Financial Calculations Summary --}}
        <div class="bg-white rounded-lg shadow p-6 text-sm">
            <h3 class="font-semibold text-gray-700 mb-4 border-b pb-2">সারসংক্ষেপ (Summary)</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <p class="text-gray-500">{{ __('ui.invoice.gross') }}</p>
                    <p class="text-lg font-semibold text-gray-800 mt-0.5">@taka($sale->gross())</p>
                </div>
                @if($sale->itemDiscount() > 0)
                    <div>
                        <p class="text-gray-500">{{ __('ui.sale.item_discount') }}</p>
                        <p class="text-lg font-semibold text-gray-800 mt-0.5">@taka($sale->itemDiscount())</p>
                    </div>
                @endif
                <div>
                    <p class="text-gray-500">{{ __('ui.invoice.bill_discount') }}</p>
                    <p class="text-lg font-semibold text-gray-800 mt-0.5">@taka($sale->discount)</p>
                </div>
                <div>
                    <p class="text-gray-500">{{ __('ui.invoice.net') }}</p>
                    <p class="text-lg font-semibold text-green-700 mt-0.5">@taka($sale->net())</p>
                </div>
                <div>
                    <p class="text-gray-500">{{ __('ui.invoice.paid') }}</p>
                    <p class="text-lg font-semibold text-blue-700 mt-0.5">@taka($sale->paid_amount)</p>
                    @if($paymentAccount && $sale->paid_amount > 0)
                        <span class="block text-xs text-gray-400 mt-1 font-normal">({{ $paymentAccount->name }})</span>
                    @endif
                </div>
                @if($sale->due() > 0.005)
                    <div>
                        <p class="text-gray-500">{{ __('ui.invoice.due') }}</p>
                        <p class="text-lg font-semibold text-red-600 mt-0.5">@taka($sale->due())</p>
                    </div>
                @endif
                @can('cost.view')
                    <div>
                        <p class="text-gray-500">{{ __('ui.report.cogs') }}</p>
                        <p class="text-lg font-semibold text-amber-700 mt-0.5">@taka($sale->cogs())</p>
                    </div>
                    <div>
                        <p class="text-gray-500">{{ __('ui.report.profit') }}</p>
                        <p class="text-lg font-semibold mt-0.5 @if($sale->profit() >= 0) text-emerald-600 @else text-rose-600 @endif">
                            @taka($sale->profit())
                        </p>
                    </div>
                @endcan
            </div>
        </div>

        {{-- Items Table --}}
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <div class="px-4 py-3 border-b">
                <h3 class="font-semibold text-gray-700">পণ্য তালিকা (Items)</h3>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.sale.product') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.sale.qty') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.sale.price') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.sale.discount') }}</th>
                        @can('cost.view')
                            <th class="px-4 py-2 text-right">{{ __('ui.product.cost_price') }}</th>
                        @endcan
                        <th class="px-4 py-2 text-right">{{ __('ui.invoice.line_total') }}</th>
                        @can('cost.view')
                            <th class="px-4 py-2 text-right">{{ __('ui.report.profit') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-medium text-gray-900">{{ $item->product->name }}</span>
                                @if($item->product->sku)
                                    <span class="block text-xs text-gray-400 mt-0.5">SKU: {{ $item->product->sku }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono">
                                {{ rtrim(rtrim(number_format($item->qty, 3), '0'), '.') }}
                                <span class="text-gray-400 text-xs font-sans">{{ $item->product->unit }}</span>
                            </td>
                            <td class="px-4 py-2 text-right font-mono">@taka($item->unit_price)</td>
                            <td class="px-4 py-2 text-right font-mono">
                                @if ((float) $item->discount > 0)
                                    @taka($item->discount)
                                @else
                                    —
                                @endif
                            </td>
                            @can('cost.view')
                                <td class="px-4 py-2 text-right font-mono text-amber-700">@taka($item->cost_price)</td>
                            @endcan
                            <td class="px-4 py-2 text-right font-mono font-medium">
                                @taka($item->lineRevenue() - (float) $item->discount)
                            </td>
                            @can('cost.view')
                                @php($itemProfit = ($item->lineRevenue() - (float) $item->discount) - $item->lineCogs())
                                <td class="px-4 py-2 text-right font-mono font-medium @if($itemProfit >= 0) text-emerald-600 @else text-rose-600 @endif">
                                    @taka($itemProfit)
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($sale->notes)
            <div class="bg-white rounded-lg shadow p-6 text-sm">
                <h3 class="font-semibold text-gray-700 mb-2 border-b pb-2">নোট (Notes)</h3>
                <p class="text-gray-600 whitespace-pre-wrap">{{ $sale->notes }}</p>
            </div>
        @endif

        {{-- Ledger Entries / Double-Entry Audit --}}
        @can('cost.view')
            @if(count($ledgerEntries) > 0)
                <div class="bg-white rounded-lg shadow p-6 text-sm space-y-6">
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-1 border-b pb-2">{{ __('ui.sale.ledger_postings') }}</h3>
                        <p class="text-xs text-gray-500">হিসাবরক্ষণের সুবিধার জন্য জেনারেট হওয়া জার্নাল এন্ট্রি ও ডেবিট/ক্রেডিট সমূহ নিচে দেখানো হলো:</p>
                    </div>

                    @foreach($ledgerEntries as $entry)
                        <div class="border rounded-lg p-4 space-y-3 bg-gray-50">
                            <div class="flex justify-between items-center text-xs text-gray-500 font-medium border-b pb-1.5">
                                <span>ভাউচার নং: #{{ $entry->id }} ({{ $entry->reference_type === 'Sale' ? 'বিক্রয় ও আয়' : 'বিক্রীত পণ্যের কস্ট' }})</span>
                                <span>তারিখ: {{ $entry->date->format('d/m/Y') }}</span>
                            </div>
                            <div class="text-sm font-semibold text-gray-800 mb-1">{{ $entry->description }}</div>
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-gray-500 border-b text-left">
                                        <th class="py-1 font-semibold">{{ __('ui.account.title') }}</th>
                                        <th class="text-right py-1 w-28 font-semibold">{{ __('ui.payment.debit') }}</th>
                                        <th class="text-right py-1 w-28 font-semibold">{{ __('ui.payment.credit') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($entry->lines as $line)
                                        <tr>
                                            <td class="py-1.5">
                                                <span class="font-mono text-gray-500">{{ $line->account->code }}</span>
                                                <span class="ml-1 font-medium">{{ $line->account->name }}</span>
                                            </td>
                                            <td class="text-right py-1.5 font-mono text-gray-900">
                                                {{ (float) $line->debit > 0 ? \App\Support\Money::taka($line->debit) : '—' }}
                                            </td>
                                            <td class="text-right py-1.5 font-mono text-gray-900">
                                                {{ (float) $line->credit > 0 ? \App\Support\Money::taka($line->credit) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-gray-300 font-semibold">
                                        <td class="py-1.5">মোট (Total)</td>
                                        <td class="text-right py-1.5 font-mono text-gray-900">@taka($entry->lines->sum('debit'))</td>
                                        <td class="text-right py-1.5 font-mono text-gray-900">@taka($entry->lines->sum('credit'))</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endforeach
                </div>
            @endif
        @endcan
    </div>
</x-app-layout>
