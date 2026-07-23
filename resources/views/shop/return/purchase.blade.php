<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.return.purchase_title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        <form method="POST" action="{{ route('returns.purchase.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{ items: [{ product_id: '', qty: 1 }] }">
            @csrf
            <table class="min-w-full text-sm">
                <thead class="text-gray-500 text-left">
                    <tr>
                        <th class="py-1">{{ __('ui.return.product') }}</th>
                        <th class="py-1 w-28">{{ __('ui.return.qty') }}</th>
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
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-1 pe-2"><input :name="`items[${idx}][qty]`" x-model="item.qty" type="number" step="0.001" min="0" class="w-full rounded border-gray-300 text-sm"></td>
                            <td class="py-1"><button type="button" @click="items.splice(idx,1)" x-show="items.length>1" class="text-red-500">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="items.push({ product_id:'', qty:1 })" class="text-sm text-gray-600">+ {{ __('ui.purchase.add_line') }}</button>

            <div class="grid grid-cols-3 gap-4 border-t pt-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.return.refund') }}</label>
                    <input name="refund_amount" type="number" step="0.01" min="0" value="0" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.return.account') }}</label>
                    <select name="refund_account_id" class="{{ $input }}">
                        @foreach ($paymentAccounts as $a)
                            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                    <input name="date" type="date" value="{{ now()->toDateString() }}" required class="{{ $input }}">
                </div>
            </div>

            <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.return.save') }}</button>
        </form>
    </div>
</x-app-layout>
