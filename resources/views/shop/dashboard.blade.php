@php
    $card = fn ($label, $value) => "<div class='bg-white rounded-lg shadow p-5'><div class='text-sm text-gray-500'>$label</div><div class='mt-1 text-2xl font-semibold text-gray-800'>$value</div></div>";
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.dashboard.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="mb-4 text-sm {{ $openingLocked ? 'text-green-700' : 'text-yellow-700' }}">
            {{ $openingLocked ? __('ui.dashboard.opening_locked') : __('ui.dashboard.opening_not_locked') }}
        </div>

        {{-- Quick actions, role-gated --}}
        @php
            $actions = [];
            if (auth()->user()->can('sale.create'))     $actions[] = ['sales.create', __('ui.nav.sales')];
            if (auth()->user()->can('purchase.create')) $actions[] = ['purchases.create', __('ui.nav.purchases')];
            if (auth()->user()->can('expense.create'))  $actions[] = ['expenses.create', __('ui.nav_more.expense')];
            if (auth()->user()->can('payment.manage'))  $actions[] = ['payments.create', __('ui.nav_more.payment')];
        @endphp
        @if (count($actions))
            <div class="mb-6 flex flex-wrap gap-2">
                @foreach ($actions as [$route, $label])
                    <a href="{{ route($route) }}" class="bg-gray-800 text-white rounded px-4 py-2 text-sm hover:bg-gray-700">+ {{ $label }}</a>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {!! $card(__('ui.dashboard.today_sales'), \App\Support\Money::taka($todaySales)) !!}
            {!! $card(__('ui.dashboard.month_sales'), \App\Support\Money::taka($monthSales)) !!}
            {!! $card(__('ui.dashboard.cash_balance'), \App\Support\Money::taka($cash)) !!}
            {!! $card(__('ui.dashboard.stock_value'), \App\Support\Money::taka($stockValue)) !!}
            {!! $card(__('ui.dashboard.receivable'), \App\Support\Money::taka($receivable)) !!}
            {!! $card(__('ui.dashboard.payable'), \App\Support\Money::taka($payable)) !!}
            @if (! is_null($monthProfit))
                {!! $card(__('ui.dashboard.month_profit'), \App\Support\Money::taka($monthProfit)) !!}
            @endif
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Low-stock alerts --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                    <h3 class="font-medium text-gray-800">{{ __('ui.dashboard.low_stock') }}</h3>
                    @can('report.view')
                        <a href="{{ route('reports.low_stock') }}" class="text-xs text-blue-600 hover:underline">{{ __('ui.report.low_stock') }}</a>
                    @endcan
                </div>
                @if ($lowStock->isEmpty())
                    <p class="px-5 py-6 text-sm text-gray-400">{{ __('ui.dashboard.low_stock_none') }}</p>
                @else
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            @foreach ($lowStock->take(6) as $row)
                                <tr>
                                    <td class="px-5 py-2">{{ $row['name'] }}</td>
                                    <td class="px-5 py-2 text-right text-red-600 whitespace-nowrap">
                                        {{ rtrim(rtrim(number_format($row['qty'], 3), '0'), '.') }} {{ $row['unit'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Recent activity, report viewers only --}}
            @can('report.view')
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-5 py-3 border-b flex items-center justify-between">
                        <h3 class="font-medium text-gray-800">{{ __('ui.dashboard.recent_activity') }}</h3>
                        <a href="{{ route('reports.day_book') }}" class="text-xs text-blue-600 hover:underline">{{ __('ui.report.day_book') }}</a>
                    </div>
                    @if ($recent->isEmpty())
                        <p class="px-5 py-6 text-sm text-gray-400">{{ __('ui.common.no_data') }}</p>
                    @else
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y">
                                @foreach ($recent as $e)
                                    <tr>
                                        <td class="px-5 py-2 whitespace-nowrap text-gray-500">{{ $e->date->format('d/m/Y') }}</td>
                                        <td class="px-5 py-2">{{ $e->description }}</td>
                                        <td class="px-5 py-2 text-right whitespace-nowrap">@taka($e->totalDebit())</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
