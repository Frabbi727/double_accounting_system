<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ $product->name }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('products.edit', $product) }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.product.edit') }}</a>
                <a href="{{ route('products.index') }}" class="text-gray-500 px-3 py-1.5 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')

        {{-- Summary --}}
        <div class="bg-white rounded-lg shadow p-6 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">{{ __('ui.product.category') }}</p>
                <p class="font-medium">{{ $product->category?->full_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.product.sku') }}</p>
                <p class="font-medium">{{ $product->sku ?? '—' }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.product.unit') }}</p>
                <p class="font-medium">{{ $product->unit }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.product.current_stock') }}</p>
                <p class="font-medium">{{ rtrim(rtrim(number_format($product->currentStock(), 3), '0'), '.') }} {{ $product->unit }}</p>
            </div>
            @can('cost.view')
                <div>
                    <p class="text-gray-500">{{ __('ui.product.cost_price') }}</p>
                    <p class="font-medium">@taka($product->cost_price)</p>
                </div>
            @endcan
            <div>
                <p class="text-gray-500">{{ __('ui.product.sale_price') }}</p>
                <p class="font-medium">@taka($product->sale_price)</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.product.reorder') }}</p>
                <p class="font-medium">{{ $product->reorder_level }}</p>
            </div>
            <div>
                <p class="text-gray-500">{{ __('ui.common.status') }}</p>
                <p class="font-medium">{{ $product->is_active ? __('ui.product.active') : __('ui.product.inactive') }}</p>
            </div>
        </div>

        {{-- Full movement history: kobe, koto dame, kon source (Purchase/Sale/Opening) --}}
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <div class="px-4 py-3 border-b">
                <h3 class="font-semibold text-gray-700">{{ __('ui.product.history') }}</h3>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('ui.product.source') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.product.in') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.product.out') }}</th>
                        @can('cost.view')
                            <th class="px-4 py-2 text-right">{{ __('ui.product.cost_price') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($movements as $m)
                        @php($qty = (float) $m->qty)
                        <tr>
                            <td class="px-4 py-2">{{ $m->date->format('d-m-Y') }}</td>
                            <td class="px-4 py-2">
                                @php($label = __('ui.ref_type.'.$m->reference_type))
                                @if ($m->reference_type === 'Purchase' && $m->reference_id)
                                    @can('purchase.create')
                                        <a href="{{ route('purchases.print', $m->reference_id) }}" class="text-blue-600 hover:underline">{{ $label }} #{{ $m->reference_id }}</a>
                                    @else
                                        {{ $label }}
                                    @endcan
                                @elseif ($m->reference_type === 'Sale' && $m->reference_id)
                                    @can('sale.create')
                                        <a href="{{ route('sales.print', $m->reference_id) }}" class="text-blue-600 hover:underline">{{ $label }} #{{ $m->reference_id }}</a>
                                    @else
                                        {{ $label }}
                                    @endcan
                                @else
                                    {{ $label }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right text-green-700">{{ $qty > 0 ? rtrim(rtrim(number_format($qty, 3), '0'), '.') : '' }}</td>
                            <td class="px-4 py-2 text-right text-red-700">{{ $qty < 0 ? rtrim(rtrim(number_format(abs($qty), 3), '0'), '.') : '' }}</td>
                            @can('cost.view')
                                <td class="px-4 py-2 text-right">{{ $m->unit_cost !== null ? '৳'.number_format((float) $m->unit_cost, 2) : '—' }}</td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
