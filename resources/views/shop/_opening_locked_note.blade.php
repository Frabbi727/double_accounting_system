{{-- Shown on master create forms when the business has already started
     (opening period locked), so opening balances can't be posted. Guides the
     owner back to setup mode instead of hitting a wall. --}}
<div class="border-t pt-4">
    <div class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
        {{ __('ui.opening.master_locked_note') }}
        @can('opening.manage')
            <a href="{{ route('opening.index') }}" class="font-medium underline">{{ __('ui.opening.back_to_setup') }}</a>
        @endcan
    </div>
</div>
