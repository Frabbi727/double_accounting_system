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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {!! $card(__('ui.dashboard.today_sales'), \App\Support\Money::taka($todaySales)) !!}
            {!! $card(__('ui.dashboard.cash_balance'), \App\Support\Money::taka($cash)) !!}
            {!! $card(__('ui.dashboard.stock_value'), \App\Support\Money::taka($stockValue)) !!}
            {!! $card(__('ui.dashboard.receivable'), \App\Support\Money::taka($receivable)) !!}
            {!! $card(__('ui.dashboard.payable'), \App\Support\Money::taka($payable)) !!}
            @if (! is_null($monthProfit))
                {!! $card(__('ui.dashboard.month_profit'), \App\Support\Money::taka($monthProfit)) !!}
            @endif
        </div>
    </div>
</x-app-layout>
