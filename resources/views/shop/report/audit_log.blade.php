@php
    // Localized labels for the journal reference types; falls back to the raw token.
    $typeLabels = [
        'Opening' => __('ui.report.audit_log'),
        'Sale' => __('ui.nav.sales'),
        'SaleCOGS' => __('ui.report.cogs'),
        'Purchase' => __('ui.nav.purchases'),
        'Expense' => __('ui.nav_more.expense'),
        'PaymentIn' => __('ui.nav_more.payment'),
        'PaymentOut' => __('ui.nav_more.payment'),
        'Transfer' => __('ui.nav_more.transfer'),
        'SaleReturn' => __('ui.nav_more.returns'),
        'PurchaseReturn' => __('ui.nav_more.returns'),
        'IncentiveIn' => __('ui.nav_more.incentive'),
        'IncentiveOut' => __('ui.nav_more.incentive'),
        'Rebate' => __('ui.nav_more.rebate'),
    ];
    $label = fn ($t) => $typeLabels[$t] ?? $t;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.audit_log') }}</h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('reports.audit_log') }}" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-lg shadow p-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('ui.report.audit_type') }}</label>
                <select name="type" class="border-gray-300 rounded text-sm">
                    <option value="">{{ __('ui.report.audit_all_types') }}</option>
                    @foreach ($types as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ $label($t) }} ({{ $t }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('ui.report.from') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="border-gray-300 rounded text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('ui.report.to') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="border-gray-300 rounded text-sm">
            </div>
            <button type="submit" class="bg-gray-800 text-white rounded px-4 py-1.5 text-sm">{{ __('ui.report.go') }}</button>
        </form>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2 whitespace-nowrap">{{ __('ui.report.audit_time') }}</th>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.audit_type') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.description') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.audit_user') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.common.amount') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.audit_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($entries as $e)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $e->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $label($e->reference_type) }}</td>
                            <td class="px-4 py-2">{{ $e->description }}</td>
                            <td class="px-4 py-2">{{ $e->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">@taka($e->totalDebit())</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($e->isReversal())
                                    <span class="text-amber-700">{{ __('ui.report.audit_reversal') }}</span>
                                @elseif ($e->isReversed())
                                    <span class="text-red-600">{{ __('ui.report.audit_reversed') }}</span>
                                @else
                                    <span class="text-green-700">{{ __('ui.report.audit_live') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
