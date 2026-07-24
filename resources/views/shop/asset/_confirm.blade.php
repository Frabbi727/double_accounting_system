{{-- Pre-submit asset confirmation. Lives inside the create form, so its Confirm
     button submits the same fields. Shows the full breakdown + the exact double
     entry that will be posted, so the user understands what will happen. --}}
<div x-show="confirming" x-cloak style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-gray-900/50" @click="confirming = false"></div>

    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4"
         @keydown.escape.window="confirming = false">
        <h3 class="font-semibold text-lg text-gray-800">{{ __('asset.confirm_title') }}</h3>
        <p class="text-xs text-gray-500">{{ __('asset.confirm_intro') }}</p>

        {{-- Asset facts --}}
        <dl class="text-sm divide-y">
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('asset.name') }}</dt>
                <dd class="font-medium text-gray-800" x-text="name"></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('asset.category') }}</dt>
                <dd class="text-gray-800" x-text="selectedCategory ? selectedCategory.name : ''"></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('asset.purchase_date') }}</dt>
                <dd class="text-gray-800" x-text="date"></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('asset.amount') }}</dt>
                <dd class="text-lg font-bold text-gray-900" x-text="taka(amount)"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="vendorLabel">
                <dt class="text-gray-500">{{ __('asset.vendor') }}</dt>
                <dd class="text-gray-800" x-text="vendorLabel"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="referenceNo">
                <dt class="text-gray-500">{{ __('asset.reference_no') }}</dt>
                <dd class="text-gray-800" x-text="referenceNo"></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="docCount > 0">
                <dt class="text-gray-500">{{ __('asset.confirm_documents') }}</dt>
                <dd class="text-gray-800" x-text="docCount"></dd>
            </div>
        </dl>

        {{-- The double entry that will be posted --}}
        <div class="rounded border bg-gray-50 p-3 space-y-1">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('asset.confirm_entry') }}</p>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">{{ __('asset.confirm_debit') }}: <span class="text-gray-900 font-medium" x-text="debitLabel"></span></span>
                <span class="font-medium" x-text="taka(amount)"></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">{{ __('asset.confirm_credit') }}: <span class="text-gray-900 font-medium" x-text="creditLabel"></span></span>
                <span class="font-medium" x-text="taka(amount)"></span>
            </div>
        </div>

        <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="confirming = false" class="text-gray-500 px-4 py-2 text-sm">
                {{ __('asset.confirm_back') }}
            </button>
            <button type="submit" class="bg-green-600 text-white rounded px-4 py-2 text-sm">
                {{ __('asset.confirm_yes') }}
            </button>
        </div>
    </div>
</div>
