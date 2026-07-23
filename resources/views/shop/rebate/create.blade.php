<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.rebate.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('rebates.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{ reducePayable: false }">
            @csrf
            <p class="text-xs text-gray-500">{{ __('ui.rebate.help') }}</p>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.rebate.product') }}</label>
                <select name="product_id" required class="{{ $input }}">
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.rebate.amount') }}</label>
                    <input name="amount" type="number" step="0.01" min="0" required class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                    <input name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required class="{{ $input }}">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="hidden" name="reduce_payable" value="0">
                <input type="checkbox" name="reduce_payable" value="1" x-model="reducePayable" class="rounded border-gray-300">
                {{ __('ui.rebate.reduce_payable') }}
            </label>
            <div x-show="! reducePayable">
                <label class="text-sm text-gray-600">{{ __('ui.rebate.account') }}</label>
                <select name="account_id" class="{{ $input }}">
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.rebate.note') }}</label>
                <input name="notes" class="{{ $input }}">
            </div>
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.rebate.save') }}</button>
                <a href="{{ route('dashboard') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
