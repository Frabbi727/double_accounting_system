<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.assets_title')"
    :help="__('ui.opening.wizard.assets_help')">

    @if ($categories->isEmpty())
        <div class="flex flex-col items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <span class="leading-relaxed">{{ __('ui.opening.wizard.assets_no_category') }}</span>
            <a href="{{ route('asset-categories.index') }}"
               class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 text-white px-3 py-1.5 text-sm font-medium hover:bg-amber-700 transition">
                {{ __('ui.opening.wizard.assets_add_category') }}
            </a>
        </div>
    @else
        @php($input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500')

        <form method="POST" action="{{ route('opening.setup.assets') }}"
              class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 space-y-4">
            @csrf
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('ui.opening.wizard.add_new') }}</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.asset_category') }}</label>
                    <select name="asset_category_id" required class="{{ $input }}">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('asset_category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.asset_name') }}</label>
                    <input name="name" required value="{{ old('name') }}" class="{{ $input }}">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.asset_value') }}</label>
                    <input name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0"
                           class="{{ $input }} text-right">
                    @error('amount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-900 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-gray-800 transition">
                    <span aria-hidden="true">+</span> {{ __('ui.opening.wizard.add') }}
                </button>
            </div>
        </form>

        @include('shop.opening.setup._added_list', ['rows' => $rows, 'emptyKey' => 'assets_empty'])
    @endif
</x-opening-wizard>
