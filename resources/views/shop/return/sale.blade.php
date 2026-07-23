<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.return.sale_title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        {{-- Step 1: pick a sale --}}
        <form method="GET" action="{{ route('returns.sale') }}" class="bg-white rounded-lg shadow p-6 mb-6 flex items-end gap-3">
            <div class="flex-1">
                <label class="text-sm text-gray-600">{{ __('ui.return.select_sale') }}</label>
                <select name="sale_id" class="{{ $input }}">
                    <option value="">—</option>
                    @foreach ($sales as $s)
                        <option value="{{ $s->id }}" @selected($sale && $sale->id === $s->id)>
                            {{ $s->date->format('d/m/Y') }} — {{ $s->invoice_no ?? '#'.$s->id }} ({{ \App\Support\Money::taka($s->net()) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="bg-gray-600 text-white rounded px-4 py-2 text-sm">{{ __('ui.return.load') }}</button>
        </form>

        {{-- Step 2: return quantities --}}
        @if ($sale)
            <form method="POST" action="{{ route('returns.sale.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4">
                @csrf
                <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">{{ __('ui.return.product') }}</th>
                            <th class="py-1 text-right">{{ __('ui.sale.qty') }}</th>
                            <th class="py-1 text-right">{{ __('ui.sale.price') }}</th>
                            <th class="py-1 w-28">{{ __('ui.return.return_qty') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($sale->items as $i => $item)
                            <tr>
                                <td class="py-2">{{ $item->product->name }}</td>
                                <td class="py-2 text-right">{{ rtrim(rtrim(number_format($item->qty, 3), '0'), '.') }}</td>
                                <td class="py-2 text-right">@taka($item->unit_price)</td>
                                <td class="py-2">
                                    <input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}">
                                    <input name="items[{{ $i }}][qty]" type="number" step="0.001" min="0" max="{{ $item->qty }}" value="0" class="w-full rounded border-gray-300 text-sm">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

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
        @endif
    </div>
</x-app-layout>
