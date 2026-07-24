<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.product.edit') }} — {{ $product->name }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @include('shop.product._form', [
            'action' => route('products.update', $product),
            'product' => $product,
        ])
    </div>
</x-app-layout>
