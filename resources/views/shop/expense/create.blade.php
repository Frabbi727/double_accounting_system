<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.expense.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('expenses.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{
                  accountId: @js((string) ($paymentAccounts->first()->id ?? '')),
                  amount: @js((string) old('amount', '')),
                  accountBalances: @js($paymentAccountBalances),
                  get sourceBalance() {
                      const b = this.accountBalances[this.accountId];
                      return b === undefined || b === null ? null : Number(b);
                  },
                  get insufficient() {
                      return this.sourceBalance !== null && Number(this.amount) > this.sourceBalance + 0.005;
                  },
                  fmt(n) { return '৳ ' + Number(n).toLocaleString('bn-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
              }">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.expense.account') }}</label>
                <select name="expense_account_id" required class="{{ $input }}">
                    @foreach ($expenseAccounts as $a)
                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.expense.amount') }}</label>
                    <input name="amount" type="number" step="0.01" min="0" required class="{{ $input }}"
                           x-model="amount" :class="insufficient ? 'border-red-400 ring-red-300' : ''">
                    <p class="mt-1 text-xs text-red-600" x-show="insufficient"
                       x-text="'{{ __('ui.finance.insufficient') }}'"></p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                    <input name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.expense.from') }}</label>
                    <select name="payment_account_id" x-model="accountId" class="{{ $input }}">
                        @foreach ($paymentAccounts as $a)
                            <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="sourceBalance !== null">
                        {{ __('ui.finance.available') }}: <span class="font-semibold" x-text="fmt(sourceBalance)"></span>
                    </p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.expense.note') }}</label>
                    <input name="notes" class="{{ $input }}">
                </div>
            </div>
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="insufficient">{{ __('ui.expense.save') }}</button>
                <a href="{{ route('expenses.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
