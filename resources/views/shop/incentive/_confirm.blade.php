{{-- Pre-submit confirmation dialog. Included inside the incentive/rebate form,
     so it reads the form's Alpine state directly. Shows every detail of what is
     about to be posted, to catch mistakes before they hit the ledger. --}}
<div x-show="confirming" x-cloak style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-gray-900/50" @click="confirming=false"></div>

    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6 space-y-4"
         @keydown.escape.window="confirming=false">
        <h3 class="font-semibold text-lg text-gray-800">{{ __('ui.incentive.confirm_title') }}</h3>
        <p class="text-xs text-gray-500">{{ __('ui.incentive.confirm_intro') }}</p>

        <dl class="text-sm divide-y">
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.incentive.kind') }}</dt>
                <dd class="font-medium">{{ $confirmKind }}</dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.incentive.direction') }}</dt>
                <dd class="font-medium" x-text="directionLabel"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="partyName">
                <dt class="text-gray-500">{{ __('ui.incentive.party') }}</dt>
                <dd class="font-medium" x-text="partyName"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="typeof productName !== 'undefined' && productName">
                <dt class="text-gray-500">{{ __('ui.rebate.product') }}</dt>
                <dd class="font-medium" x-text="typeof productName !== 'undefined' ? productName : ''"></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.incentive.basis') }}</dt>
                <dd class="font-medium" x-text="basisLabel"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="basis!=='fixed'">
                <dt class="text-gray-500">{{ __('ui.incentive.base') }} × %</dt>
                <dd class="font-medium"><span x-text="fmt(base)"></span> × <span x-text="(Number(rate)||0)+'%'"></span></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.incentive.amount') }}</dt>
                <dd class="font-bold text-gray-900" x-text="amount===null ? '{{ __('ui.incentive.computed') }} (server)' : fmt(amount)"></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.incentive.settle_mode') }}</dt>
                <dd class="font-medium" x-text="settleLabel"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="settleMode==='due' && due!==null">
                <dt class="text-gray-500">{{ __('ui.incentive.current_due') }}</dt>
                <dd class="font-medium" x-text="fmt(due)"></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.incentive.date') }}</dt>
                <dd class="font-medium" x-text="date"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="notes">
                <dt class="text-gray-500">{{ __('ui.incentive.note') }}</dt>
                <dd class="font-medium" x-text="notes"></dd>
            </div>
        </dl>

        <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="confirming=false" class="text-gray-500 px-4 py-2 text-sm">
                {{ __('ui.incentive.confirm_back') }}
            </button>
            <button type="submit" class="bg-green-600 text-white rounded px-4 py-2 text-sm">
                {{ __('ui.incentive.confirm_yes') }}
            </button>
        </div>
    </div>
</div>
