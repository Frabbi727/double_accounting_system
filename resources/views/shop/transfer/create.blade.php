<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.transfer.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('transfers.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.transfer.from') }}</label>
                    <select name="from_account_id" required class="{{ $input }}">
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.transfer.to') }}</label>
                    <select name="to_account_id" required class="{{ $input }}">
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.transfer.amount') }}</label>
                    <input name="amount" type="number" step="0.01" min="0" required class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                    <input name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required class="{{ $input }}">
                </div>
            </div>
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.transfer.save') }}</button>
                <a href="{{ route('dashboard') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
