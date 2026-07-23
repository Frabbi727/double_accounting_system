<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.opening.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('ui.opening.summary') }}</h3>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt>{{ __('ui.opening.total_assets') }}</dt><dd>@taka($totalAssets)</dd></div>
                <div class="flex justify-between"><dt>{{ __('ui.opening.total_liabilities') }}</dt><dd>@taka($totalLiabilities)</dd></div>
                <div class="flex justify-between font-semibold border-t pt-2"><dt>{{ __('ui.opening.total_equity') }}</dt><dd>@taka($totalEquity)</dd></div>
            </dl>

            <div class="mt-6">
                @if ($locked)
                    <p class="text-green-700 text-sm">{{ __('ui.opening.locked_note') }}</p>
                @else
                    <p class="text-gray-600 text-sm mb-4">{{ __('ui.opening.unlocked_note') }}</p>
                    <form method="POST" action="{{ route('opening.lock') }}"
                          onsubmit="return confirm('{{ __('ui.opening.confirm_lock') }}')">
                        @csrf
                        <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.opening.lock') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
