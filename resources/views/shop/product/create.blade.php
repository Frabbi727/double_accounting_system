<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.product.add') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <form method="POST" action="{{ route('products.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf
            @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.product.name') }}</label>
                <input name="name" value="{{ old('name') }}" required class="{{ $input }}">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.product.unit') }}</label>
                    <input name="unit" value="{{ old('unit', 'pcs') }}" required class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.product.reorder') }}</label>
                    <input name="reorder_level" type="number" min="0" value="{{ old('reorder_level', 0) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.product.cost_price') }}</label>
                    <input name="cost_price" type="number" step="0.0001" min="0" value="{{ old('cost_price', 0) }}" required class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.product.sale_price') }}</label>
                    <input name="sale_price" type="number" step="0.01" min="0" value="{{ old('sale_price', 0) }}" required class="{{ $input }}">
                </div>
            </div>

            <fieldset class="border-t pt-4">
                <legend class="text-sm font-medium text-gray-700">{{ __('ui.product.opening_qty') }}</legend>
                <div class="grid grid-cols-2 gap-4 mt-2">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('ui.product.opening_qty') }}</label>
                        <input name="opening_qty" type="number" step="0.001" min="0" value="{{ old('opening_qty') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('ui.product.opening_cost') }}</label>
                        <input name="opening_cost" type="number" step="0.0001" min="0" value="{{ old('opening_cost') }}" class="{{ $input }}">
                    </div>
                </div>
            </fieldset>

            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.common.save') }}</button>
                <a href="{{ route('products.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
