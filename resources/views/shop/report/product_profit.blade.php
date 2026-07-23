<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.product_profit') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="GET" class="mb-4 flex items-end gap-3 flex-wrap">
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.report.from') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="mt-1 rounded border-gray-300 shadow-sm text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.report.to') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="mt-1 rounded border-gray-300 shadow-sm text-sm">
            </div>
            <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.report.go') }}</button>
        </form>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.product.name') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.sold') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.revenue') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.cogs') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.profit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($report['rows'] as $r)
                        <tr>
                            <td class="px-4 py-2">{{ $r['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($r['qty'], 3), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-right">@taka($r['revenue'])</td>
                            <td class="px-4 py-2 text-right">@taka($r['cogs'])</td>
                            <td class="px-4 py-2 text-right {{ $r['profit'] < 0 ? 'text-red-700' : 'text-green-700' }}">@taka($r['profit'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ __('ui.report.total') }}</td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2 text-right">@taka($report['total_revenue'])</td>
                        <td class="px-4 py-2 text-right">@taka($report['total_cogs'])</td>
                        <td class="px-4 py-2 text-right">@taka($report['total_profit'])</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
