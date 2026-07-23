<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.sale.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        <form method="POST" action="{{ route('sales.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{
                  products: @js($products->mapWithKeys(fn($p) => [$p->id => array_filter([
                      'price' => (float) $p->sale_price,
                      'cost'  => auth()->user()->can('cost.view') ? (float) $p->cost_price : null,
                  ], fn($v) => $v !== null)])),
                  customerDiscounts: @js($customerDiscounts),
                  customerId: '',
                  paid: 0,
                  billDiscountValue: 0,
                  billDiscountMode: 'flat',
                  items: [{ product_id: '', qty: 1, unit_price: 0, cost: 0, discountValue: 0, discountMode: 'flat' }],

                  onProductChange(i) {
                      const p = this.products[i.product_id];
                      if (p) { i.unit_price = p.price; i.cost = p.cost ?? 0; }
                  },
                  onCustomerChange() {
                      const pct = Number(this.customerDiscounts[this.customerId] || 0);
                      if (pct > 0) { this.billDiscountMode = 'pct'; this.billDiscountValue = pct; }
                  },

                  lineGross(i) { return Number(i.qty || 0) * Number(i.unit_price || 0); },
                  lineDiscountTaka(i) {
                      const raw = i.discountMode === 'pct'
                          ? this.lineGross(i) * Number(i.discountValue || 0) / 100
                          : Number(i.discountValue || 0);
                      return Math.min(Math.max(raw, 0), this.lineGross(i));
                  },
                  lineNet(i) { return this.lineGross(i) - this.lineDiscountTaka(i); },
                  get itemsNet() { return this.items.reduce((s, i) => s + this.lineNet(i), 0); },

                  get billDiscountTaka() {
                      const raw = this.billDiscountMode === 'pct'
                          ? this.itemsNet * Number(this.billDiscountValue || 0) / 100
                          : Number(this.billDiscountValue || 0);
                      return Math.min(Math.max(raw, 0), this.itemsNet);
                  },
                  get net() { return this.itemsNet - this.billDiscountTaka; },
                  get due() { return this.net - Number(this.paid || 0); },
                  get hasCustomer() { return this.customerId !== '' && this.customerId != null; },
                  get needsCustomer() { return this.due > 0.005 && !this.hasCustomer; },

                  lineCost(i) { return Number(i.cost || 0) * Number(i.qty || 0); },
                  lineBelowCost(i) { return i.product_id !== '' && this.lineNet(i) < this.lineCost(i) - 0.005; },
                  lineLoss(i) { return this.lineCost(i) - this.lineNet(i); },
                  get totalCost() { return this.items.reduce((s, i) => s + this.lineCost(i), 0); },
                  get totalLoss() { return this.totalCost - this.net; },
                  get showTotalLoss() { return this.net < this.totalCost - 0.005; }
              }">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.sale.customer') }}</label>
                    <select name="customer_id" x-model="customerId" @change="onCustomerChange()" class="{{ $input }}">
                        <option value="">—</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                    <input name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required class="{{ $input }}">
                </div>
            </div>

            <table class="min-w-full text-sm mt-2">
                <thead class="text-gray-500 text-left">
                    <tr>
                        <th class="py-1">{{ __('ui.sale.product') }}</th>
                        <th class="py-1 w-20">{{ __('ui.sale.qty') }}</th>
                        <th class="py-1 w-24">{{ __('ui.sale.price') }}</th>
                        <th class="py-1 w-36">{{ __('ui.sale.discount') }}</th>
                        <th class="py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in items" :key="idx">
                        <tr :class="lineBelowCost(item) ? 'align-top' : 'align-top'">
                            <td class="py-1 pe-2">
                                <select :name="`items[${idx}][product_id]`" x-model="item.product_id" @change="onProductChange(item)" required class="w-full rounded border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                @can('cost.view')
                                    <p x-show="lineBelowCost(item)" class="text-xs text-red-600 mt-1">
                                        ⚠ {{ __('ui.sale.below_cost') }} — {{ __('ui.sale.loss_warning') }} ৳<span x-text="lineLoss(item).toFixed(2)"></span>
                                    </p>
                                @endcan
                            </td>
                            <td class="py-1 pe-2"><input :name="`items[${idx}][qty]`" x-model="item.qty" type="number" step="0.001" min="0" class="w-full rounded border-gray-300 text-sm"></td>
                            <td class="py-1 pe-2"><input :name="`items[${idx}][unit_price]`" x-model="item.unit_price" type="number" step="0.01" min="0" class="w-full rounded border-gray-300 text-sm"></td>
                            <td class="py-1 pe-2">
                                <div class="flex gap-1">
                                    <input x-model="item.discountValue" type="number" step="0.01" min="0" class="w-full rounded border-gray-300 text-sm">
                                    <select x-model="item.discountMode" class="rounded border-gray-300 text-sm px-1">
                                        <option value="flat">৳</option>
                                        <option value="pct">%</option>
                                    </select>
                                    {{-- Submitted value is always the resolved taka amount. --}}
                                    <input type="hidden" :name="`items[${idx}][discount]`" :value="lineDiscountTaka(item).toFixed(2)">
                                </div>
                            </td>
                            <td class="py-1"><button type="button" @click="items.splice(idx,1)" x-show="items.length>1" class="text-red-500">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="items.push({ product_id:'', qty:1, unit_price:0, cost:0, discountValue:0, discountMode:'flat' })" class="text-sm text-gray-600">+ {{ __('ui.sale.add_line') }}</button>

            <div class="grid grid-cols-3 gap-4 border-t pt-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.sale.bill_discount') }}</label>
                    <div class="flex gap-1 mt-1">
                        <input x-model="billDiscountValue" type="number" step="0.01" min="0" class="block w-full rounded border-gray-300 shadow-sm text-sm">
                        <select x-model="billDiscountMode" class="rounded border-gray-300 text-sm px-1">
                            <option value="flat">৳</option>
                            <option value="pct">%</option>
                        </select>
                    </div>
                    <input type="hidden" name="discount" :value="billDiscountTaka.toFixed(2)">
                    <p x-show="billDiscountMode === 'pct'" class="text-xs text-gray-500 mt-1">= ৳<span x-text="billDiscountTaka.toFixed(2)"></span></p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.sale.paid') }}</label>
                    <input name="paid_amount" x-model="paid" type="number" step="0.01" min="0" value="0" class="{{ $input }}">
                </div>
                <div class="text-end self-end">
                    <span class="text-sm text-gray-500">{{ __('ui.sale.net') }}:</span>
                    <span class="text-lg font-semibold" x-text="net.toFixed(2)"></span>
                    @can('cost.view')
                        <p x-show="showTotalLoss" class="text-xs text-red-600 mt-1">
                            ⚠ {{ __('ui.sale.loss_warning') }} ৳<span x-text="totalLoss.toFixed(2)"></span>
                        </p>
                    @endcan
                </div>
            </div>

            <div>
                <label class="text-sm text-gray-600">{{ __('ui.sale.deposit_to') }}</label>
                <select name="payment_account_id" class="{{ $input }}">
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}" @selected($a->id == $defaultAccountId)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="needsCustomer" x-cloak class="rounded border border-red-300 bg-red-50 text-red-700 text-sm px-3 py-2 flex items-center justify-between gap-3">
                <span>⚠ {{ __('ui.sale.need_customer_for_credit') }}</span>
                <a href="{{ route('customers.create') }}" target="_blank" class="underline whitespace-nowrap">+ {{ __('ui.sale.add_customer') }}</a>
            </div>

            <div class="flex gap-3">
                <button :disabled="needsCustomer" :class="needsCustomer ? 'opacity-50 cursor-not-allowed' : ''" class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.sale.save') }}</button>
                <a href="{{ route('sales.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
