{{-- Shared incentive/rebate history table. Expects:
     $entries   Collection<PartyIncentive>
     $remaining array<string,float> keyed "type:id" — live remaining due
     $showProduct bool --}}
@php
    $basisKey = [
        'fixed' => 'ui.incentive.basis_fixed',
        'pct_of_due' => 'ui.incentive.basis_pct_due',
        'pct_of_invoice' => 'ui.incentive.basis_pct_invoice',
        'pct_of_product_value' => 'ui.incentive.basis_pct_product',
        'pct_of_sales' => 'ui.incentive.basis_pct_sales',
    ];
@endphp
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-left">
            <tr>
                <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                <th class="px-4 py-2">{{ __('ui.incentive.party') }}</th>
                @if ($showProduct ?? false)
                    <th class="px-4 py-2">{{ __('ui.rebate.product') }}</th>
                @endif
                <th class="px-4 py-2">{{ __('ui.incentive.basis') }}</th>
                <th class="px-4 py-2">{{ __('ui.incentive.settle') }}</th>
                <th class="px-4 py-2 text-right">{{ __('ui.incentive.amount') }}</th>
                <th class="px-4 py-2 text-right">{{ __('ui.incentive.remaining_due') }}</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($entries as $e)
                @php($party = $e->party())
                @php($showRoute = $e->kind === 'rebate' ? route('rebates.show', $e) : route('incentives.show', $e))
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $e->date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">{{ $party?->name ?? '—' }}</td>
                    @if ($showProduct ?? false)
                        <td class="px-4 py-2">{{ $e->product?->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-2">
                        {{ __($basisKey[$e->basis] ?? 'ui.incentive.basis_fixed') }}
                        @if ($e->basis !== 'fixed')
                            <span class="text-gray-400">({{ rtrim(rtrim(number_format($e->rate, 2), '0'), '.') }}%)</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ __('ui.incentive.settle_'.$e->settle_mode) }}</td>
                    <td class="px-4 py-2 text-right">@taka($e->amount)</td>
                    <td class="px-4 py-2 text-right">
                        @if ($e->party_id)
                            @taka($remaining[$e->party_type.':'.$e->party_id] ?? 0)
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ $showRoute }}" class="text-blue-600 hover:underline">{{ __('ui.incentive.details') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ ($showProduct ?? false) ? 8 : 7 }}" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
