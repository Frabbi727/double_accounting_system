<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('return.details_title') }} — {{ $return->return_no }}</h2>
            <a href="{{ route('returns.index') }}" class="text-gray-500 text-sm hover:underline">{{ __('ui.common.back') ?? 'Back' }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')
        @error('cancel')<div class="rounded bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>@enderror

        @if ($return->isCancelled())
            <div class="rounded bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                {{ __('return.cancelled_banner', [
                    'by' => $return->canceller?->name ?? '—',
                    'at' => $return->cancelled_at?->format('d/m/Y H:i'),
                ]) }}
                @if ($return->cancel_reason) — {{ $return->cancel_reason }} @endif
            </div>
        @endif

        {{-- Meta: invoice + customer + audit --}}
        <div class="bg-white rounded-lg shadow p-6 grid grid-cols-2 gap-y-2 gap-x-6 text-sm">
            <div><span class="text-gray-500">{{ __('return.invoice_no') }}:</span>
                <a href="{{ route('sales.show', $return->sale_id) }}" class="text-blue-600 hover:underline">{{ $return->sale?->invoice_no ?? '#'.$return->sale_id }}</a>
            </div>
            <div><span class="text-gray-500">{{ __('return.customer') }}:</span> {{ $return->customer?->name ?? __('return.walk_in') }}</div>
            <div><span class="text-gray-500">{{ __('return.return_date') }}:</span> {{ $return->date->format('d/m/Y') }}</div>
            <div><span class="text-gray-500">{{ __('return.status') }}:</span>
                @if ($return->isCancelled())
                    <span class="text-red-600">{{ __('return.status_cancelled') }}</span>
                @else
                    <span class="text-green-600">{{ __('return.status_completed') }}</span>
                @endif
            </div>
            <div><span class="text-gray-500">{{ __('return.refund_account') }}:</span> {{ $return->refundAccount?->code }} — {{ $return->refundAccount?->name }}</div>
            <div><span class="text-gray-500">{{ __('return.created_by') }}:</span> {{ $return->creator?->name ?? '—' }} · {{ $return->created_at?->format('d/m/Y H:i') }}</div>
            @if ($return->reason)<div><span class="text-gray-500">{{ __('return.reason') }}:</span> {{ $return->reason }}</div>@endif
            @if ($return->notes)<div><span class="text-gray-500">{{ __('return.notes') }}:</span> {{ $return->notes }}</div>@endif
        </div>

        {{-- Returned products --}}
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('return.product') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('return.returned_qty') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('return.unit_price') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('return.returned_amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($return->items as $item)
                        <tr>
                            <td class="px-4 py-2">{{ $item->product?->name }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($item->qty, 3), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-right">@taka($item->unit_price)</td>
                            <td class="px-4 py-2 text-right">@taka($item->lineAmount())</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-sm border-t">
                    <tr><td colspan="3" class="px-4 py-1 text-right text-gray-500">{{ __('return.returned_amount') }}</td><td class="px-4 py-1 text-right">@taka($return->returnedAmount())</td></tr>
                    @if ($return->deduction() > 0)
                        <tr><td colspan="3" class="px-4 py-1 text-right text-gray-500">{{ __('return.deduction') }}</td><td class="px-4 py-1 text-right">− @taka($return->deduction())</td></tr>
                    @endif
                    <tr class="font-semibold"><td colspan="3" class="px-4 py-1 text-right">{{ __('return.final_refund') }}</td><td class="px-4 py-1 text-right">@taka($return->finalRefund())</td></tr>
                </tfoot>
            </table>
        </div>

        {{-- Accounting entries --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="font-semibold text-gray-700 mb-3">{{ __('return.accounting_entries') }}</h3>
            @foreach (array_filter([$return->revenueEntry, $return->cogsEntry]) as $entry)
                @include('shop.return._entry', ['entry' => $entry])
                @if ($entry->reversedBy)
                    @include('shop.return._entry', ['entry' => $entry->reversedBy])
                @endif
            @endforeach
        </div>

        {{-- Inventory movements --}}
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <div class="p-6 pb-2"><h3 class="font-semibold text-gray-700">{{ __('return.inventory_movements') }}</h3></div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('return.product') }}</th>
                        <th class="px-4 py-2">{{ __('return.movement') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('return.qty') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($movements as $m)
                        <tr>
                            <td class="px-4 py-2">{{ $m->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $m->product?->name }}</td>
                            <td class="px-4 py-2">{{ $m->reference_type === 'SaleReturnCancel' ? __('return.movement_out') : __('return.movement_in') }}</td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($m->qty, 3), '0'), '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Cancel (owner only) --}}
        @can('entry.delete')
            @unless ($return->isCancelled())
                <div class="bg-white rounded-lg shadow p-6" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="text-red-600 text-sm hover:underline">{{ __('return.cancel') }}</button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('returns.cancel', $return) }}" class="mt-3 flex items-end gap-3"
                          @submit="return confirm(@js(__('return.confirm_cancel')))">
                        @csrf
                        <div class="flex-1">
                            <label class="text-sm text-gray-600">{{ __('return.cancel_reason') }}</label>
                            <input name="cancel_reason" required maxlength="255" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
                        </div>
                        <button class="bg-red-600 text-white rounded px-4 py-2 text-sm">{{ __('return.cancel') }}</button>
                    </form>
                </div>
            @endunless
        @endcan
    </div>
</x-app-layout>
