<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.product.title') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('product-categories.index') }}" class="border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm">{{ __('ui.nav.categories') }}</a>
                <a href="{{ route('products.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.product.add') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.product.name') }}</th>
                        <th class="px-4 py-2">{{ __('ui.product.category') }}</th>
                        <th class="px-4 py-2">{{ __('ui.product.unit') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.product.current_stock') }}</th>
                        @can('cost.view')
                            <th class="px-4 py-2 text-right">{{ __('ui.product.cost_price') }}</th>
                        @endcan
                        <th class="px-4 py-2 text-right">{{ __('ui.product.sale_price') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($products as $p)
                        <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                            <td class="px-4 py-2">
                                <a href="{{ route('products.show', $p) }}" class="text-blue-600 hover:underline">{{ $p->name }}</a>
                                @unless ($p->is_active)<span class="ml-1 text-xs text-gray-400">({{ __('ui.product.inactive') }})</span>@endunless
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ $p->category?->full_name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $p->unit }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($p->currentStock(), 3), '0'), '.') }}</td>
                            @can('cost.view')
                                <td class="px-4 py-2 text-right">@taka($p->cost_price)</td>
                            @endcan
                            <td class="px-4 py-2 text-right">@taka($p->sale_price)</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('products.edit', $p) }}" class="text-blue-600 hover:underline">{{ __('ui.product.edit') }}</a>
                                <form method="POST" action="{{ route('products.destroy', $p) }}" class="inline ml-2"
                                      onsubmit="return confirm('{{ __('ui.product.delete_confirm') }}')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">{{ __('ui.product.delete') }}</button>
                                </form>
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
