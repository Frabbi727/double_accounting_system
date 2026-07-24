<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.products_title')"
    :help="__('ui.opening.wizard.products_help')">

    <form method="POST" action="{{ route('opening.setup.products') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
        @csrf
        <div class="sm:col-span-4">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.product_name') }}</label>
            <input name="name" required value="{{ old('name') }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.qty') }}</label>
            <input name="opening_qty" type="number" step="0.01" min="0" value="{{ old('opening_qty') }}" placeholder="0"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm text-right">
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.cost') }}</label>
            <input name="cost_price" type="number" step="0.01" min="0" required value="{{ old('cost_price') }}" placeholder="0"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm text-right">
            @error('cost_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="text-xs font-medium text-gray-600">{{ __('ui.opening.wizard.sale_price') }}</label>
            <input name="sale_price" type="number" step="0.01" min="0" required value="{{ old('sale_price') }}" placeholder="0"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm text-right">
            @error('sale_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="w-full bg-gray-800 text-white rounded px-3 py-2 text-sm hover:bg-gray-700">
                + {{ __('ui.opening.wizard.add') }}
            </button>
        </div>
    </form>

    <p class="text-xs text-gray-400">{{ __('ui.opening.wizard.products_cost_note') }}</p>

    {{-- Running list of products with opening stock. --}}
    <div class="border-t pt-4">
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-400">{{ __('ui.opening.wizard.products_empty') }}</p>
        @else
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                {{ __('ui.opening.wizard.added_count', ['count' => $rows->count()]) }}
            </p>
            <dl class="text-sm divide-y">
                @foreach ($rows as $row)
                    <div class="flex justify-between py-1.5">
                        <dt class="text-gray-700">{{ $row['name'] }}</dt>
                        <dd class="text-gray-600">
                            {{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') }} {{ $row['unit'] }}
                            <span class="text-gray-400">× @taka($row['cost'])</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</x-opening-wizard>
