<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.product.title') }}</h2>
            <a href="{{ route('products.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.product.add') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.product.name') }}</th>
                        <th class="px-4 py-2">{{ __('ui.product.unit') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.product.current_stock') }}</th>
                        @can('cost.view')
                            <th class="px-4 py-2 text-right">{{ __('ui.product.cost_price') }}</th>
                        @endcan
                        <th class="px-4 py-2 text-right">{{ __('ui.product.sale_price') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($products as $p)
                        <tr>
                            <td class="px-4 py-2">{{ $p->name }}</td>
                            <td class="px-4 py-2">{{ $p->unit }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($p->currentStock(), 3), '0'), '.') }}</td>
                            @can('cost.view')
                                <td class="px-4 py-2 text-right">@taka($p->cost_price)</td>
                            @endcan
                            <td class="px-4 py-2 text-right">@taka($p->sale_price)</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
