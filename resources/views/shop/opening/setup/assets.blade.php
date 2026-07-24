<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.assets_title')"
    :help="__('ui.opening.wizard.assets_help')">

    @if ($categories->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {{ __('ui.opening.wizard.assets_no_category') }}
            <a href="{{ route('asset-categories.index') }}" class="font-medium underline">
                {{ __('ui.opening.wizard.assets_add_category') }}
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('opening.setup.assets') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @csrf
            <div class="sm:col-span-4">
                <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.asset_category') }}</label>
                <select name="asset_category_id" required
                        class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('asset_category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-4">
                <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.asset_name') }}</label>
                <input name="name" required value="{{ old('name') }}"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
                @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.asset_value') }}</label>
                <input name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0"
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm text-right">
                @error('amount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full bg-gray-800 text-white rounded px-3 py-2 text-sm hover:bg-gray-700">
                    + {{ __('ui.opening.wizard.add') }}
                </button>
            </div>
        </form>

        @include('shop.opening.setup._added_list', ['rows' => $rows, 'emptyKey' => 'assets_empty'])
    @endif
</x-opening-wizard>
