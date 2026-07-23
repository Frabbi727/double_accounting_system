@php
    $reports = [
        ['reports.trial_balance', __('ui.report.trial_balance')],
        ['reports.balance_sheet', __('ui.report.balance_sheet')],
        ['reports.profit_loss',   __('ui.report.profit_loss')],
        ['reports.day_book',      __('ui.report.day_book')],
        ['reports.cash_book',     __('ui.report.cash_book')],
        ['reports.stock',         __('ui.report.stock')],
        ['reports.low_stock',     __('ui.report.low_stock')],
        ['reports.customer_due',  __('ui.report.customer_due')],
        ['reports.supplier_due',  __('ui.report.supplier_due')],
        ['reports.aging',         __('ui.report.aging')],
        ['reports.party_statement', __('ui.report.party_statement')],
        ['reports.audit_log',       __('ui.report.audit_log')],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($reports as [$route, $label])
                <a href="{{ route($route) }}" class="block bg-white rounded-lg shadow p-5 hover:shadow-md hover:bg-gray-50 transition">
                    <span class="text-gray-800 font-medium">{{ $label }}</span>
                </a>
            @endforeach

            @can('cost.view')
                <a href="{{ route('reports.product_profit') }}" class="block bg-white rounded-lg shadow p-5 hover:shadow-md hover:bg-gray-50 transition">
                    <span class="text-gray-800 font-medium">{{ __('ui.report.product_profit') }}</span>
                </a>
            @endcan
        </div>
    </div>
</x-app-layout>
