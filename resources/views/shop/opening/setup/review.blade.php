<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.review_title')"
    :help="__('ui.opening.wizard.review_help')">

    @php($t = $summary['totals'])

    <div x-data="{ confirming: false }" class="space-y-5">
        {{-- Balanced status --}}
        <div class="text-sm font-medium {{ $t['balanced'] ? 'text-green-700' : 'text-red-700' }}">
            {{ $t['balanced'] ? __('ui.report.balanced') : __('ui.report.not_balanced') }}
        </div>

        {{-- Condensed figures --}}
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
            <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.opening_cash_position') }}</dt><dd class="font-medium">@taka($t['opening_cash'])</dd></div>
            <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_supplier_due') }}</dt><dd class="font-medium">@taka($summary['suppliers']['total'])</dd></div>
            <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_customer_due') }}</dt><dd class="font-medium">@taka($summary['customers']['total'])</dd></div>
            <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.inventory_value') }}</dt><dd class="font-medium">@taka($summary['inventory']['total_value'])</dd></div>
            <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_assets') }}</dt><dd class="font-medium">@taka($t['total_assets'])</dd></div>
            <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_equity') }}</dt><dd class="font-medium">@taka($t['total_equity'])</dd></div>
        </dl>

        {{-- Soft warnings (missing info) --}}
        @if (count($summary['warnings']) > 0)
            <ul class="space-y-2 text-sm">
                @foreach ($summary['warnings'] as $w)
                    <li class="flex items-start gap-2 {{ $w['severity'] === 'blocker' ? 'text-red-700' : 'text-amber-700' }}">
                        <span>{{ $w['severity'] === 'blocker' ? '⛔' : '⚠️' }}</span>
                        <span>{{ __('ui.opening.warn.' . $w['key']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Lock action --}}
        <div class="border-t pt-4">
            <p class="text-sm text-gray-600 mb-3">{{ __('ui.opening.wizard.review_lock_note') }}</p>
            <button type="button"
                    @click="confirming = true"
                    @disabled($summary['has_blocker'])
                    class="bg-green-600 text-white rounded px-5 py-2 text-sm font-medium hover:bg-green-700 {{ $summary['has_blocker'] ? 'opacity-50 cursor-not-allowed' : '' }}">
                {{ __('ui.opening.wizard.finish_lock') }}
            </button>
        </div>

        @unless ($summary['has_blocker'])
            @include('shop.opening._confirm')
        @endunless
    </div>
</x-opening-wizard>
