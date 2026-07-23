<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.sale.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        <form method="POST" action="{{ route('sales.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{
                  items: [{ product_id: '', qty: 1, unit_price: 0, discount: 0 }],
                  billDiscount: 0,
                  lineTotal(i) { return (i.qty * i.unit_price) - i.discount; },
                  get net() { return this.items.reduce((s,i)=>s+this.lineTotal(i),0) - Number(this.billDiscount||0); }
              }">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.sale.customer') }}</label>
                    <select name="customer_id" class="{{ $input }}">
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
                        <th class="py-1 w-24">{{ __('ui.sale.discount') }}</th>
                        <th class="py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in items" :key="idx">
                        <tr>
                            <td class="py-1 pe-2">
                                <select :name="`items[${idx}][product_id]`" x-model="item.product_id" required class="w-full rounded border-gray-300 text-sm">
                                    <option value="">—</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id }}" data-price="{{ $p->sale_price }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-1 pe-2"><input :name="`items[${idx}][qty]`" x-model="item.qty" type="number" step="0.001" min="0" class="w-full rounded border-gray-300 text-sm"></td>
                            <td class="py-1 pe-2"><input :name="`items[${idx}][unit_price]`" x-model="item.unit_price" type="number" step="0.01" min="0" class="w-full rounded border-gray-300 text-sm"></td>
                            <td class="py-1 pe-2"><input :name="`items[${idx}][discount]`" x-model="item.discount" type="number" step="0.01" min="0" class="w-full rounded border-gray-300 text-sm"></td>
                            <td class="py-1"><button type="button" @click="items.splice(idx,1)" x-show="items.length>1" class="text-red-500">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="items.push({ product_id:'', qty:1, unit_price:0, discount:0 })" class="text-sm text-gray-600">+ {{ __('ui.sale.add_line') }}</button>

            <div class="grid grid-cols-3 gap-4 border-t pt-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.sale.bill_discount') }}</label>
                    <input name="discount" x-model="billDiscount" type="number" step="0.01" min="0" value="0" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.sale.paid') }}</label>
                    <input name="paid_amount" type="number" step="0.01" min="0" value="0" class="{{ $input }}">
                </div>
                <div class="text-end self-end">
                    <span class="text-sm text-gray-500">{{ __('ui.sale.net') }}:</span>
                    <span class="text-lg font-semibold" x-text="net.toFixed(2)"></span>
                </div>
            </div>

            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.sale.save') }}</button>
                <a href="{{ route('sales.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
