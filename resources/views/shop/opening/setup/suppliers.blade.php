<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.suppliers_title')"
    :help="__('ui.opening.wizard.suppliers_help')">

    @php($input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500')

    <form method="POST" action="{{ route('opening.setup.suppliers') }}"
          class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 space-y-4">
        @csrf
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('ui.opening.wizard.add_new') }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.party_name') }}</label>
                <input name="name" required value="{{ old('name') }}" class="{{ $input }}">
                @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.phone') }}</label>
                <input name="phone" value="{{ old('phone') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.owed_amount') }}</label>
                <input name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" placeholder="0"
                       class="{{ $input }} text-right">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-900 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-gray-800 transition">
                <span aria-hidden="true">+</span> {{ __('ui.opening.wizard.add') }}
            </button>
        </div>
    </form>

    @include('shop.opening.setup._added_list', ['rows' => $rows, 'emptyKey' => 'suppliers_empty'])
</x-opening-wizard>
