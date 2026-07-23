{{-- Shared party (customer/supplier) statement table.
     Expects: $statement (from ReportService::partyStatement).
     Optional: $linkable (bool) — when true, Sale/Purchase rows link to their
     bill/invoice print so the item-level detail is one click away. --}}
@php($linkable = $linkable ?? false)
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-left">
            <tr>
                <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                <th class="px-4 py-2">{{ __('ui.report.description') }}</th>
                <th class="px-4 py-2 text-right">{{ __('ui.report.charge') }}</th>
                <th class="px-4 py-2 text-right">{{ __('ui.report.payment') }}</th>
                <th class="px-4 py-2 text-right">{{ __('ui.report.balance') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <tr class="bg-gray-50/50">
                <td class="px-4 py-2" colspan="4">{{ __('ui.report.opening') }}</td>
                <td class="px-4 py-2 text-right font-medium">@taka($statement['opening'])</td>
            </tr>
            @forelse ($statement['rows'] as $row)
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">
                        @php($ref = $row['reference_type'] ?? null)
                        @if ($linkable && $ref === 'Sale' && ($row['reference_id'] ?? null))
                            @can('sale.create')
                                <a href="{{ route('sales.print', $row['reference_id']) }}" class="text-indigo-600 hover:underline">{{ $row['description'] }}</a>
                            @else
                                {{ $row['description'] }}
                            @endcan
                        @elseif ($linkable && $ref === 'Purchase' && ($row['reference_id'] ?? null))
                            @can('purchase.create')
                                <a href="{{ route('purchases.print', $row['reference_id']) }}" class="text-indigo-600 hover:underline">{{ $row['description'] }}</a>
                            @else
                                {{ $row['description'] }}
                            @endcan
                        @else
                            {{ $row['description'] }}
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">{{ $row['charge'] > 0 ? \App\Support\Money::taka($row['charge']) : '' }}</td>
                    <td class="px-4 py-2 text-right">{{ $row['payment'] > 0 ? \App\Support\Money::taka($row['payment']) : '' }}</td>
                    <td class="px-4 py-2 text-right">@taka($row['balance'])</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot class="bg-gray-50 font-semibold">
            <tr>
                <td class="px-4 py-2" colspan="2">{{ $statement['record']->name }} — {{ __('ui.report.closing') }}</td>
                <td class="px-4 py-2 text-right">@taka($statement['total_charge'])</td>
                <td class="px-4 py-2 text-right">@taka($statement['total_payment'])</td>
                <td class="px-4 py-2 text-right">@taka($statement['closing'])</td>
            </tr>
        </tfoot>
    </table>
</div>
