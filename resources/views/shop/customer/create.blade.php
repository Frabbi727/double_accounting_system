<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.customer.add') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('customers.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.customer.name') }}</label>
                <input name="name" value="{{ old('name') }}" required class="{{ $input }}">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.customer.phone') }}</label>
                    <input name="phone" value="{{ old('phone') }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.customer.credit_limit') }}</label>
                    <input name="credit_limit" type="number" step="0.01" min="0" value="{{ old('credit_limit', 0) }}" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.customer.address') }}</label>
                <input name="address" value="{{ old('address') }}" class="{{ $input }}">
            </div>
            @if ($openingLocked ?? false)
                @include('shop._opening_locked_note')
            @else
                <fieldset class="border-t pt-4">
                    <legend class="text-sm font-medium text-gray-700">{{ __('ui.customer.opening_due') }}</legend>
                    <div class="grid grid-cols-2 gap-4 mt-2">
                        <div>
                            <label class="text-sm text-gray-600">{{ __('ui.customer.opening_due') }}</label>
                            <input name="opening_amount" type="number" step="0.01" min="0" value="{{ old('opening_amount') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="text-sm text-gray-600">{{ __('ui.customer.opening_date') }}</label>
                            <input name="opening_date" type="date" value="{{ old('opening_date') }}" class="{{ $input }}">
                        </div>
                    </div>
                </fieldset>
            @endif
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.common.save') }}</button>
                <a href="{{ route('customers.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
