<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.suppliers_title')"
    :help="__('ui.opening.wizard.suppliers_help')">

    <form method="POST" action="{{ route('opening.setup.suppliers') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        @csrf
        <div class="sm:col-span-5">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.party_name') }}</label>
            <input name="name" required value="{{ old('name') }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-3">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.phone') }}</label>
            <input name="phone" value="{{ old('phone') }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.owed_amount') }}</label>
            <input name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" placeholder="0"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm text-right">
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="w-full bg-gray-800 text-white rounded px-3 py-2 text-sm hover:bg-gray-700">
                + {{ __('ui.opening.wizard.add') }}
            </button>
        </div>
    </form>

    @include('shop.opening.setup._added_list', ['rows' => $rows, 'emptyKey' => 'suppliers_empty'])
</x-opening-wizard>
