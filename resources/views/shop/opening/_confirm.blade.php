{{-- Pre-lock confirmation. Shares the parent's x-data ({ confirming }). Its
     Confirm button submits the real POST to opening.lock, so the owner
     explicitly acknowledges the consequences before the irreversible lock. --}}
<div x-show="confirming" x-cloak style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-gray-900/50" @click="confirming = false"></div>

    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4"
         @keydown.escape.window="confirming = false">
        <h3 class="font-semibold text-lg text-gray-800">{{ __('ui.opening.confirm_title') }}</h3>
        <p class="text-xs text-gray-500">{{ __('ui.opening.confirm_intro') }}</p>

        <ul class="text-sm text-gray-700 space-y-1 list-disc ps-5">
            <li>{{ __('ui.opening.confirm_point1') }}</li>
            <li>{{ __('ui.opening.confirm_point2') }}</li>
            <li>{{ __('ui.opening.confirm_point3') }}</li>
            <li>{{ __('ui.opening.confirm_point4') }}</li>
        </ul>

        {{-- Condensed recap --}}
        <div class="rounded border bg-gray-50 p-3 space-y-1">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('ui.opening.confirm_recap') }}</p>
            <div class="flex justify-between text-sm"><span class="text-gray-600">{{ __('ui.opening.total_assets') }}</span><span class="font-medium">@taka($summary['totals']['total_assets'])</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-600">{{ __('ui.opening.total_liabilities') }}</span><span class="font-medium">@taka($summary['totals']['total_liabilities'])</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-600">{{ __('ui.opening.total_equity') }}</span><span class="font-medium">@taka($summary['totals']['total_equity'])</span></div>
            <div class="flex justify-between text-sm pt-1 border-t {{ $summary['totals']['balanced'] ? 'text-green-700' : 'text-red-700' }}">
                <span>{{ $summary['totals']['balanced'] ? __('ui.report.balanced') : __('ui.report.not_balanced') }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('opening.lock') }}" class="flex gap-3 justify-end pt-2">
            @csrf
            <button type="button" @click="confirming = false" class="text-gray-500 px-4 py-2 text-sm">
                {{ __('ui.opening.confirm_back') }}
            </button>
            <button type="submit" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700">
                {{ __('ui.opening.confirm_yes') }}
            </button>
        </form>
    </div>
</div>
