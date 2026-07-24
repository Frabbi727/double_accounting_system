<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.account.edit_opening_title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('accounts.opening.update', $account) }}" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf
            <div class="flex justify-between items-center border-b pb-3">
                <div class="text-sm text-gray-500">{{ $account->code }}</div>
                <div class="font-medium text-gray-800">{{ $account->name }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.account.opening_amount') }}</label>
                <input name="amount" type="number" step="0.01" min="0" value="{{ old('amount', number_format($current, 2, '.', '')) }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.account.opening_reason') }}</label>
                <input name="reason" value="{{ old('reason') }}" placeholder="{{ __('ui.account.opening_reason_ph') }}" class="{{ $input }}">
            </div>
            <p class="text-xs text-gray-500 bg-gray-50 rounded p-3">{{ __('ui.account.opening_note') }}</p>
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.common.save') }}</button>
                <a href="{{ route('accounts.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
