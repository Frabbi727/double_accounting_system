<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.stock') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.product.name') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.product.current_stock') }}</th>
                        @can('cost.view')
                            <th class="px-4 py-2 text-right">{{ __('ui.product.cost_price') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('ui.report.value') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($rows as $r)
                        <tr class="{{ $r['low_stock'] ? 'bg-yellow-50' : '' }}">
                            <td class="px-4 py-2">{{ $r['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($r['qty'], 3), '0'), '.') }} {{ $r['unit'] }}</td>
                            @can('cost.view')
                                <td class="px-4 py-2 text-right">@taka($r['cost_price'])</td>
                                <td class="px-4 py-2 text-right">@taka($r['value'])</td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                @can('cost.view')
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-4 py-2" colspan="3">{{ __('ui.report.total') }}</td>
                            <td class="px-4 py-2 text-right">@taka($total_value)</td>
                        </tr>
                    </tfoot>
                @endcan
            </table>
        </div>
    </div>
</x-app-layout>
