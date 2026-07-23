<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.payment.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        @php($dir = old('direction', $prefillDirection ?? 'received'))
        @php($partyId = old('party_id', $prefillPartyId ?? ''))
        @php($amtInit = old('amount', ! is_null($prefillDue ?? null) && $prefillDue > 0 ? $prefillDue : ''))
        <form method="POST" action="{{ route('payments.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{
                  direction: @js($dir),
                  partyId: @js((string) $partyId),
                  amount: @js((string) $amtInit),
                  customerDues: @js($customerDues),
                  supplierDues: @js($supplierDues),
                  get due() {
                      const map = this.direction === 'made' ? this.supplierDues : this.customerDues;
                      const d = map[this.partyId];
                      return d === undefined || d === null ? null : Number(d);
                  },
                  get over() { return this.due !== null && Number(this.amount) > this.due + 0.005; },
                  get invalid() { return !this.partyId || !(Number(this.amount) > 0) || this.due === null || this.over; },
                  fmt(n) { return '৳ ' + Number(n).toLocaleString('bn-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
              }">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.payment.direction') }}</label>
                <select name="direction" x-model="direction" @change="partyId = ''" class="{{ $input }}">
                    <option value="received">{{ __('ui.payment.received') }}</option>
                    <option value="made">{{ __('ui.payment.made') }}</option>
                </select>
            </div>

            <div>
                <label class="text-sm text-gray-600">{{ __('ui.payment.party') }}</label>
                {{-- Both selects render statically (options in the DOM) so x-model can
                     pre-select; the inactive one is disabled so it never submits. --}}
                <select name="party_id" x-model="partyId" required
                        x-show="direction === 'received'" :disabled="direction !== 'received'" class="{{ $input }}">
                    <option value="">— {{ __('ui.report.select_party') }} —</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="party_id" x-model="partyId" required
                        x-show="direction === 'made'" :disabled="direction !== 'made'" class="{{ $input }}">
                    <option value="">— {{ __('ui.report.select_party') }} —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs" x-show="due !== null">
                    <span class="text-gray-500">{{ __('ui.payment.current_due') }}:</span>
                    <span class="font-semibold" x-text="fmt(due)"></span>
                    <button type="button" class="ml-2 text-indigo-600 hover:underline"
                            x-show="due > 0" @click="amount = due">{{ __('ui.payment.pay_full') }}</button>
                </p>
                <p class="mt-1 text-xs text-amber-600" x-show="partyId && due === null">{{ __('ui.payment.no_due') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.payment.amount') }}</label>
                    <input name="amount" type="number" step="0.01" min="0" required class="{{ $input }}"
                           x-model="amount" :max="due ?? ''"
                           :class="over ? 'border-red-400 ring-red-300' : ''">
                    <p class="mt-1 text-xs text-red-600" x-show="over"
                       x-text="'{{ __('ui.payment.over_due') }}'"></p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                    <input name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.payment.account') }}</label>
                    <select name="payment_account_id" class="{{ $input }}">
                        @foreach ($paymentAccounts as $a)
                            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.expense.note') }}</label>
                    <input name="notes" class="{{ $input }}">
                </div>
            </div>

            @error('amount')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="invalid">{{ __('ui.payment.save') }}</button>
                <a href="{{ route('dashboard') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
