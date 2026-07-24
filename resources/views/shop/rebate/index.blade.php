<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.rebate.title') }}</h2>
            <a href="{{ route('rebates.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.rebate.new') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @include('shop.incentive._list', ['entries' => $entries, 'remaining' => $remaining, 'showProduct' => true])
    </div>
</x-app-layout>
