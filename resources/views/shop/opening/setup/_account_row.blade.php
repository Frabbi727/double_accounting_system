{{-- One account with an opening-amount input. Expects $row = ['model','balance']. --}}
<div class="flex items-center justify-between gap-4 py-3">
    <div class="min-w-0">
        <p class="text-sm font-medium text-gray-800 truncate">{{ $row['model']->name }}</p>
        <p class="text-xs text-gray-400">{{ __('ui.opening.subtype.' . $row['model']->subtype) }}</p>
    </div>
    <div class="w-40 shrink-0">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 text-sm">৳</span>
            <input type="number" step="0.01" min="0"
                   name="amounts[{{ $row['model']->id }}]"
                   value="{{ $row['balance'] > 0 ? $row['balance'] : '' }}"
                   placeholder="0"
                   class="block w-full rounded-md border-gray-300 shadow-sm text-sm pl-7 text-right focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>
</div>
