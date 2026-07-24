<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.customer.details') }}</h2>
            <a href="{{ route('customers.index') }}" class="text-sm text-gray-500">{{ __('ui.common.cancel') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow p-6 mb-4 flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="text-lg font-semibold text-gray-800">{{ $record->name }}</div>
                @if ($record->phone)
                    <div class="text-sm text-gray-500">{{ __('ui.customer.phone') }}: {{ $record->phone }}</div>
                @endif
                @if ($record->address)
                    <div class="text-sm text-gray-500">{{ __('ui.customer.address') }}: {{ $record->address }}</div>
                @endif
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500">{{ __('ui.payment.current_due') }}</div>
                <div class="text-xl font-bold text-gray-800">@taka($statement['closing'])</div>
                @can('payment.manage')
                    <a href="{{ route('payments.create', ['direction' => 'received', 'party_id' => $record->id]) }}"
                       class="inline-block mt-2 bg-gray-800 text-white rounded px-4 py-1.5 text-sm">{{ __('ui.report.settle') }}</a>
                @endcan
            </div>
        </div>

        @include('shop._party_statement', ['linkable' => true])

        @include('shop._party_incentives', ['entries' => $incentives])
    </div>
</x-app-layout>
