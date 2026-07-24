<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.products_title')"
    :help="__('ui.opening.wizard.products_help')">

    @php($input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500')

    <form method="POST" action="{{ route('opening.setup.products') }}"
          class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 space-y-4">
        @csrf
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('ui.opening.wizard.add_new') }}</p>

        <div>
            <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.product_name') }}</label>
            <input name="name" required value="{{ old('name') }}" class="{{ $input }}">
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.qty') }}</label>
                <input name="opening_qty" type="number" step="0.01" min="0" value="{{ old('opening_qty') }}" placeholder="0"
                       class="{{ $input }} text-right">
            </div>
            <div>
                <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.cost') }}</label>
                <input name="cost_price" type="number" step="0.01" min="0" required value="{{ old('cost_price') }}" placeholder="0"
                       class="{{ $input }} text-right">
                @error('cost_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-gray-600">{{ __('ui.opening.wizard.sale_price') }}</label>
                <input name="sale_price" type="number" step="0.01" min="0" required value="{{ old('sale_price') }}" placeholder="0"
                       class="{{ $input }} text-right">
                @error('sale_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-between gap-4">
            <p class="text-xs text-gray-400">{{ __('ui.opening.wizard.products_cost_note') }}</p>
            <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-900 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-gray-800 transition shrink-0">
                <span aria-hidden="true">+</span> {{ __('ui.opening.wizard.add') }}
            </button>
        </div>
    </form>

    {{-- Running list of products with opening stock. --}}
    @if ($rows->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">
            {{ __('ui.opening.wizard.products_empty') }}
        </div>
    @else
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                {{ __('ui.opening.wizard.added_count', ['count' => $rows->count()]) }}
            </p>
            <ul class="rounded-lg border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                @foreach ($rows as $row)
                    <li class="flex items-center justify-between gap-4 px-4 py-2.5">
                        <span class="text-sm text-gray-700">{{ $row['name'] }}</span>
                        <span class="text-sm text-gray-600 tabular-nums">
                            {{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') }} {{ $row['unit'] }}
                            <span class="text-gray-400">× @taka($row['cost'])</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-opening-wizard>
