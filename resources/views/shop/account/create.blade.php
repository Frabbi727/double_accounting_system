<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.account.add') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('accounts.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.account.name') }}</label>
                <input name="name" value="{{ old('name') }}" required class="{{ $input }}">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.account.type') }}</label>
                    <select name="subtype" class="{{ $input }}">
                        <option value="cash">Cash / ক্যাশ</option>
                        <option value="bank">Bank / ব্যাংক</option>
                        <option value="loan">Loan / লোন</option>
                    </select>
                </div>
                @unless ($openingLocked ?? false)
                    <div>
                        <label class="text-sm text-gray-600">{{ __('ui.account.opening_balance') }}</label>
                        <input name="opening_balance" type="number" step="0.01" min="0" value="{{ old('opening_balance', 0) }}" class="{{ $input }}">
                    </div>
                @endunless
            </div>
            @if ($openingLocked ?? false)
                @include('shop._opening_locked_note')
            @endif
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.common.save') }}</button>
                <a href="{{ route('accounts.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
