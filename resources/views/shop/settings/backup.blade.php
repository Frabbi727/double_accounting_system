<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.backup.heading') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <p class="text-sm text-gray-600">{{ __('ui.backup.intro') }}</p>
            <a href="{{ route('backup.download') }}"
               class="inline-block bg-gray-800 text-white rounded px-4 py-2 text-sm hover:bg-gray-700">
                {{ __('ui.backup.download') }}
            </a>
            <p class="text-xs text-gray-400">{{ __('ui.backup.note') }}</p>
        </div>
    </div>
</x-app-layout>
