<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('return.list_title') }}</h2>
            <a href="{{ route('returns.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('return.new') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('return.return_no') }}</th>
                        <th class="px-4 py-2">{{ __('return.return_date') }}</th>
                        <th class="px-4 py-2">{{ __('return.customer') }}</th>
                        <th class="px-4 py-2">{{ __('return.invoice_no') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('return.total_items') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('return.total_refund') }}</th>
                        <th class="px-4 py-2">{{ __('return.status') }}</th>
                        <th class="px-4 py-2">{{ __('return.created_by') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($returns as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="{{ route('returns.show', $r) }}" class="text-blue-600 hover:underline font-medium">{{ $r->return_no }}</a>
                            </td>
                            <td class="px-4 py-2">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $r->customer?->name ?? __('return.walk_in') }}</td>
                            <td class="px-4 py-2">{{ $r->sale?->invoice_no ?? '#'.$r->sale_id }}</td>
                            <td class="px-4 py-2 text-right">{{ $r->items_count }}</td>
                            <td class="px-4 py-2 text-right">@taka($r->finalRefund())</td>
                            <td class="px-4 py-2">
                                @if ($r->isCancelled())
                                    <span class="inline-block rounded bg-red-100 text-red-700 px-2 py-0.5 text-xs">{{ __('return.status_cancelled') }}</span>
                                @else
                                    <span class="inline-block rounded bg-green-100 text-green-700 px-2 py-0.5 text-xs">{{ __('return.status_completed') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $r->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $returns->links() }}</div>
    </div>
</x-app-layout>
