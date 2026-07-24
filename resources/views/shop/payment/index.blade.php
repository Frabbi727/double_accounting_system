<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.payment.list') }}</h2>
            <a href="{{ route('payments.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.payment.new') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('ui.payment.direction') }}</th>
                        <th class="px-4 py-2">{{ __('ui.payment.party') }}</th>
                        <th class="px-4 py-2">{{ __('ui.payment.account') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.payment.amount') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.payment.remaining_due') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($entries as $e)
                        @php($received = $e->reference_type === 'PaymentIn')
                        @php($partyName = $received ? ($customers[$e->reference_id] ?? '—') : ($suppliers[$e->reference_id] ?? '—'))
                        @php($key = ($received ? 'customer' : 'supplier').':'.$e->reference_id)
                        <tr class="hover:bg-gray-50 {{ $e->isReversed() ? 'opacity-50' : '' }}">
                            <td class="px-4 py-2 whitespace-nowrap">{{ $e->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="{{ $received ? 'text-green-700' : 'text-red-600' }}">
                                    {{ $received ? __('ui.payment.received') : __('ui.payment.made') }}
                                </span>
                                @if ($e->isReversal())
                                    <span class="text-xs text-amber-700">({{ __('ui.report.audit_reversal') }})</span>
                                @elseif ($e->isReversed())
                                    <span class="text-xs text-red-600">({{ __('ui.report.audit_reversed') }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $partyName }}</td>
                            <td class="px-4 py-2">{{ $cashAccount($e)?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">@taka($e->totalDebit())</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">@taka($remaining[$key] ?? 0)</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('payments.show', $e) }}" class="text-blue-600 hover:underline">{{ __('ui.payment.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
