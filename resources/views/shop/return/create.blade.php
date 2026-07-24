<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('return.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        {{-- Step 1: pick a sale --}}
        <form method="GET" action="{{ route('returns.create') }}" class="bg-white rounded-lg shadow p-6 mb-6 flex items-end gap-3">
            <div class="flex-1">
                <label class="text-sm text-gray-600">{{ __('return.select_sale') }}</label>
                <select name="sale_id" class="{{ $input }}">
                    <option value="">—</option>
                    @foreach ($sales as $s)
                        <option value="{{ $s->id }}" @selected($sale && $sale->id === $s->id)>
                            {{ $s->date->format('d/m/Y') }} — {{ $s->invoice_no ?? '#'.$s->id }} ({{ \App\Support\Money::taka($s->net()) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="bg-gray-600 text-white rounded px-4 py-2 text-sm">{{ __('return.load') }}</button>
        </form>

        @if ($sale)
            @error('items')<div class="mb-4 rounded bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>@enderror

            @if ($hasDiscount)
                <div class="mb-4 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
                    {{ __('return.discount_warning') }}
                    <span class="font-medium">
                        @if ($policy === 'proportional') {{ __('return.policy_proportional') }} @else {{ __('return.policy_ignore') }} @endif
                    </span>
                </div>
            @endif

            <form method="POST" action="{{ route('returns.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
                  x-data="returnForm({
                      items: {{ Illuminate\Support\Js::from($sale->items->values()->map(fn($it) => [
                          'sale_item_id' => $it->id,
                          'name' => $it->product->name,
                          'sold' => (float) $it->qty,
                          'unit_price' => (float) $it->unit_price,
                          'returnable' => (float) ($returnable[$it->id] ?? 0),
                      ])) }},
                      accounts: {{ Illuminate\Support\Js::from($paymentAccounts->map(fn($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name])) }},
                      defaultAccountId: {{ Illuminate\Support\Js::from($defaultAccountId) }},
                      today: {{ Illuminate\Support\Js::from(now()->toDateString()) }}
                  })"
                  @submit="confirming = false">
                @csrf
                <input type="hidden" name="sale_id" value="{{ $sale->id }}">

                <table class="min-w-full text-sm">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">{{ __('return.product') }}</th>
                            <th class="py-1 text-right">{{ __('return.sold_qty') }}</th>
                            <th class="py-1 text-right">{{ __('return.unit_price') }}</th>
                            <th class="py-1 text-right">{{ __('return.returnable') }}</th>
                            <th class="py-1 w-28">{{ __('return.returned_qty') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template x-for="(item, i) in items" :key="item.sale_item_id">
                            <tr>
                                <td class="py-2" x-text="item.name"></td>
                                <td class="py-2 text-right" x-text="fmt(item.sold)"></td>
                                <td class="py-2 text-right" x-text="fmt(item.unit_price)"></td>
                                <td class="py-2 text-right" x-text="fmt(item.returnable)"></td>
                                <td class="py-2">
                                    <input type="hidden" :name="`items[${i}][sale_item_id]`" :value="item.sale_item_id">
                                    <input :name="`items[${i}][qty]`" type="number" step="0.001" min="0"
                                           :max="item.returnable" x-model.number="item.qty"
                                           :disabled="item.returnable <= 0"
                                           class="w-full rounded border-gray-300 text-sm">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div class="grid grid-cols-2 gap-4 border-t pt-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('return.deduction_type') }}</label>
                        <select name="deduction_type" x-model="deductionType" class="{{ $input }}">
                            <option value="none">{{ __('return.deduction_none') }}</option>
                            <option value="fixed">{{ __('return.deduction_fixed') }}</option>
                            <option value="percent">{{ __('return.deduction_percent') }}</option>
                        </select>
                    </div>
                    <div x-show="deductionType !== 'none'">
                        <label class="text-sm text-gray-600">{{ __('return.deduction_value') }}</label>
                        <input name="deduction_value" type="number" step="0.01" min="0" x-model.number="deductionValue" class="{{ $input }}">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('return.refund') }}</label>
                        <input name="refund_amount" type="number" step="0.01" min="0" x-model.number="refund" :max="finalRefund" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('return.refund_account') }}</label>
                        <select name="refund_account_id" x-model.number="refundAccountId" class="{{ $input }}">
                            @foreach ($paymentAccounts as $a)
                                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                        <input name="date" type="date" x-model="date" required class="{{ $input }}">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('return.reason') }}</label>
                        <input name="reason" type="text" maxlength="255" x-model="reason" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('return.notes') }}</label>
                        <input name="notes" type="text" maxlength="255" x-model="notes" class="{{ $input }}">
                    </div>
                </div>

                {{-- Live totals --}}
                <div class="border-t pt-4 space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">{{ __('return.returned_amount') }}</span><span x-text="taka(returnedAmount)"></span></div>
                    <div class="flex justify-between" x-show="deductionAmount > 0"><span class="text-gray-500">{{ __('return.deduction') }}</span><span x-text="'− ' + taka(deductionAmount)"></span></div>
                    <div class="flex justify-between font-semibold"><span>{{ __('return.final_refund') }}</span><span x-text="taka(finalRefund)"></span></div>
                    @if ($policy === 'proportional' && $hasDiscount)
                        <p class="text-xs text-amber-600">{{ __('return.proportional_note') }}</p>
                    @endif
                </div>

                <button type="button" @click="confirming = true" class="bg-gray-800 text-white rounded px-4 py-2 text-sm" :disabled="returnedAmount <= 0" :class="returnedAmount <= 0 ? 'opacity-50 cursor-not-allowed' : ''">
                    {{ __('return.review') }}
                </button>

                {{-- Pre-submit confirmation. Lives inside the form, so its Confirm button
                     submits the same fields. Shows the full breakdown so the user knows
                     exactly what will be restocked and posted. --}}
                <div x-show="confirming" x-cloak style="display:none"
                     class="fixed inset-0 z-50 flex items-center justify-center px-4">
                    <div class="absolute inset-0 bg-gray-900/50" @click="confirming = false"></div>

                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg p-6 space-y-4"
                         @keydown.escape.window="confirming = false">
                        <h3 class="font-semibold text-lg text-gray-800">{{ __('return.confirm_title') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('return.confirm_intro') }}</p>

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">{{ __('return.invoice_no') }}: <span class="text-gray-800 font-medium">{{ $sale->invoice_no ?? '#'.$sale->id }}</span></span>
                            <span class="text-gray-500">{{ __('ui.common.date') }}: <span class="text-gray-800 font-medium" x-text="date"></span></span>
                        </div>

                        {{-- Returned lines --}}
                        <div class="border rounded overflow-hidden">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-left">
                                    <tr>
                                        <th class="px-3 py-1.5">{{ __('return.product') }}</th>
                                        <th class="px-3 py-1.5 text-right">{{ __('return.returned_qty') }}</th>
                                        <th class="px-3 py-1.5 text-right">{{ __('return.unit_price') }}</th>
                                        <th class="px-3 py-1.5 text-right">{{ __('return.returned_amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <template x-for="item in returnedLines" :key="item.sale_item_id">
                                        <tr>
                                            <td class="px-3 py-1.5" x-text="item.name"></td>
                                            <td class="px-3 py-1.5 text-right" x-text="fmt(item.qty)"></td>
                                            <td class="px-3 py-1.5 text-right" x-text="fmt(item.unit_price)"></td>
                                            <td class="px-3 py-1.5 text-right font-medium" x-text="taka(item.qty * item.unit_price)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Money breakdown --}}
                        <dl class="text-sm divide-y">
                            <div class="flex justify-between py-1.5">
                                <dt class="text-gray-500">{{ __('return.returned_amount') }}</dt>
                                <dd class="font-medium" x-text="taka(returnedAmount)"></dd>
                            </div>
                            <div class="flex justify-between py-1.5" x-show="deductionAmount > 0.005">
                                <dt class="text-gray-500">{{ __('return.deduction') }}</dt>
                                <dd class="font-medium text-amber-600" x-text="'− ' + taka(deductionAmount)"></dd>
                            </div>
                            <div class="flex justify-between py-2">
                                <dt class="text-gray-700 font-semibold">{{ __('return.final_refund') }}</dt>
                                <dd class="text-lg font-bold text-gray-900" x-text="taka(finalRefund)"></dd>
                            </div>
                            <div class="flex justify-between py-1.5">
                                <dt class="text-gray-500">{{ __('return.confirm_refund_now') }} <span class="text-gray-400" x-text="'(' + refundAccountLabel + ')'"></span></dt>
                                <dd class="font-medium" x-text="taka(refundNow)"></dd>
                            </div>
                            <div class="flex justify-between py-1.5" x-show="toReceivable > 0.005">
                                <dt class="text-gray-500">{{ __('return.confirm_to_receivable') }}</dt>
                                <dd class="font-semibold text-red-600" x-text="'− ' + taka(toReceivable)"></dd>
                            </div>
                        </dl>

                        <div class="text-sm text-gray-500" x-show="reason">
                            {{ __('return.reason') }}: <span class="text-gray-800" x-text="reason"></span>
                        </div>
                        @if ($policy === 'proportional' && $hasDiscount)
                            <p class="text-xs text-amber-600">{{ __('return.proportional_note') }}</p>
                        @endif

                        <div class="flex gap-3 justify-end pt-2">
                            <button type="button" @click="confirming = false" class="text-gray-500 px-4 py-2 text-sm">
                                {{ __('return.confirm_back') }}
                            </button>
                            <button type="submit" class="bg-green-600 text-white rounded px-4 py-2 text-sm">
                                {{ __('return.confirm_yes') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>

    <script>
        function returnForm(config) {
            return {
                items: config.items.map(it => ({ ...it, qty: 0 })),
                accounts: config.accounts,
                deductionType: 'none',
                deductionValue: 0,
                refund: null,
                refundAccountId: config.defaultAccountId,
                date: config.today,
                reason: '',
                notes: '',
                confirming: false,

                fmt(n) { return (Math.round(n * 1000) / 1000).toString(); },
                taka(n) { return '৳' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

                get returnedLines() {
                    return this.items.filter(it => (Number(it.qty) || 0) > 0);
                },
                get returnedAmount() {
                    return this.items.reduce((s, it) => s + (Number(it.qty) || 0) * it.unit_price, 0);
                },
                get deductionAmount() {
                    const v = Number(this.deductionValue) || 0;
                    if (this.deductionType === 'fixed') return Math.min(v, this.returnedAmount);
                    if (this.deductionType === 'percent') return this.returnedAmount * v / 100;
                    return 0;
                },
                get finalRefund() {
                    return Math.max(this.returnedAmount - this.deductionAmount, 0);
                },
                // Empty refund field means "refund the full amount now".
                get refundNow() {
                    const r = (this.refund === null || this.refund === '') ? this.finalRefund : Number(this.refund);
                    return Math.min(Math.max(r, 0), this.finalRefund);
                },
                get toReceivable() {
                    return Math.max(this.finalRefund - this.refundNow, 0);
                },
                get refundAccountLabel() {
                    const a = this.accounts.find(x => x.id === Number(this.refundAccountId));
                    return a ? a.label : '';
                },
            };
        }
    </script>
</x-app-layout>
