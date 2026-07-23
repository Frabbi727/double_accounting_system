<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.payment.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('payments.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4"
              x-data="{ direction: 'received' }">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.payment.direction') }}</label>
                <select name="direction" x-model="direction" class="{{ $input }}">
                    <option value="received">{{ __('ui.payment.received') }}</option>
                    <option value="made">{{ __('ui.payment.made') }}</option>
                </select>
            </div>

            <div>
                <label class="text-sm text-gray-600">{{ __('ui.payment.party') }}</label>
                <select name="party_id" required class="{{ $input }}">
                    <template x-if="direction === 'received'">
                        <optgroup label="{{ __('ui.customer.title') }}">
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                    <template x-if="direction === 'made'">
                        <optgroup label="{{ __('ui.supplier.title') }}">
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.payment.amount') }}</label>
                    <input name="amount" type="number" step="0.01" min="0" required class="{{ $input }}">
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

            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.payment.save') }}</button>
                <a href="{{ route('dashboard') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
