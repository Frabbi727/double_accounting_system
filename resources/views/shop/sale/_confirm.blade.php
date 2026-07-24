{{-- Pre-submit sale confirmation. Included inside the sale form, so it reads the
     form's Alpine state directly. Shows the full money breakdown so the user can
     verify everything before the sale is posted to the ledger. --}}
<div x-show="confirming" x-cloak style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-gray-900/50" @click="confirming=false"></div>

    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4"
         @keydown.escape.window="confirming=false">
        <h3 class="font-semibold text-lg text-gray-800">{{ __('ui.sale.confirm_title') }}</h3>
        <p class="text-xs text-gray-500">{{ __('ui.sale.confirm_intro') }}</p>

        <div class="flex justify-between text-sm">
            <span class="text-gray-500">{{ __('ui.sale.customer') }}: <span class="text-gray-800 font-medium" x-text="customerLabel"></span></span>
            <span class="text-gray-500">{{ __('ui.common.date') }}: <span class="text-gray-800 font-medium" x-text="saleDate"></span></span>
        </div>

        {{-- Line items --}}
        <div class="border rounded overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-3 py-1.5">{{ __('ui.sale.product') }}</th>
                        <th class="px-3 py-1.5 text-right">{{ __('ui.sale.qty') }}</th>
                        <th class="px-3 py-1.5 text-right">{{ __('ui.sale.price') }}</th>
                        <th class="px-3 py-1.5 text-right">{{ __('ui.sale.discount') }}</th>
                        <th class="px-3 py-1.5 text-right">{{ __('ui.sale.line_total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <template x-for="(item, idx) in filledItems" :key="idx">
                        <tr>
                            <td class="px-3 py-1.5">
                                <span x-text="productName(item)"></span>
                                <span class="text-xs text-gray-400" x-text="productUnit(item) ? '('+productUnit(item)+')' : ''"></span>
                            </td>
                            <td class="px-3 py-1.5 text-right" x-text="Number(item.qty)"></td>
                            <td class="px-3 py-1.5 text-right" x-text="Number(item.unit_price).toFixed(2)"></td>
                            <td class="px-3 py-1.5 text-right" x-text="lineDiscountTaka(item).toFixed(2)"></td>
                            <td class="px-3 py-1.5 text-right font-medium" x-text="lineNet(item).toFixed(2)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <dl class="text-sm divide-y">
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.sale.subtotal') }}</dt>
                <dd class="font-medium">৳<span x-text="itemsGross.toFixed(2)"></span></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="itemsDiscount > 0.005">
                <dt class="text-gray-500">{{ __('ui.sale.discount') }} ({{ __('ui.sale.product') }})</dt>
                <dd class="font-medium text-amber-600">− ৳<span x-text="itemsDiscount.toFixed(2)"></span></dd>
            </div>
            <div class="flex justify-between py-1.5" x-show="billDiscountTaka > 0.005">
                <dt class="text-gray-500">{{ __('ui.sale.bill_discount') }}</dt>
                <dd class="font-medium text-amber-600">− ৳<span x-text="billDiscountTaka.toFixed(2)"></span></dd>
            </div>
            <div class="flex justify-between py-2">
                <dt class="text-gray-700 font-semibold">{{ __('ui.sale.net') }}</dt>
                <dd class="text-lg font-bold text-gray-900">৳<span x-text="net.toFixed(2)"></span></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.sale.paid') }}</dt>
                <dd class="font-medium">৳<span x-text="Number(paid || 0).toFixed(2)"></span></dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-500">{{ __('ui.sale.due') }}</dt>
                <dd class="font-semibold" :class="due > 0.005 ? 'text-red-600' : 'text-green-600'">৳<span x-text="due.toFixed(2)"></span></dd>
            </div>
        </dl>

        @can('cost.view')
            <p x-show="showTotalLoss" x-cloak class="text-xs text-red-600">
                ⚠ {{ __('ui.sale.loss_warning') }} ৳<span x-text="totalLoss.toFixed(2)"></span>
            </p>
        @endcan

        <div class="flex gap-3 justify-end pt-2">
            <button type="button" @click="confirming=false" class="text-gray-500 px-4 py-2 text-sm">
                {{ __('ui.sale.confirm_back') }}
            </button>
            <button type="submit" class="bg-green-600 text-white rounded px-4 py-2 text-sm">
                {{ __('ui.sale.confirm_yes') }}
            </button>
        </div>
    </div>
</div>
