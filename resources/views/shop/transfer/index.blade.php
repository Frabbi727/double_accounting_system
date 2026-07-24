<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.transfer.list') }}</h2>
            <a href="{{ route('transfers.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.transfer.new') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('ui.transfer.from') }}</th>
                        <th class="px-4 py-2">{{ __('ui.transfer.to') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.transfer.amount') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($entries as $e)
                        <tr class="hover:bg-gray-50 {{ $e->isReversed() ? 'opacity-50' : '' }}">
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ $e->date->format('d/m/Y') }}
                                @if ($e->isReversal())
                                    <span class="text-xs text-amber-700">({{ __('ui.report.audit_reversal') }})</span>
                                @elseif ($e->isReversed())
                                    <span class="text-xs text-red-600">({{ __('ui.report.audit_reversed') }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $fromAccount($e)?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $toAccount($e)?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">@taka($e->totalDebit())</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('transfers.show', $e) }}" class="text-blue-600 hover:underline">{{ __('ui.transfer.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
