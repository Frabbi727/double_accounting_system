{{-- Incentive/rebate history for one party (customer or supplier).
     Expects $entries = Collection<PartyIncentive>. --}}
@if ($entries->isNotEmpty())
    @php
        $basisKey = [
            'fixed' => 'ui.incentive.basis_fixed',
            'pct_of_due' => 'ui.incentive.basis_pct_due',
            'pct_of_invoice' => 'ui.incentive.basis_pct_invoice',
            'pct_of_product_value' => 'ui.incentive.basis_pct_product',
            'pct_of_sales' => 'ui.incentive.basis_pct_sales',
        ];
    @endphp
    <div class="mt-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('ui.incentive.title') }} / {{ __('ui.rebate.title') }}</h3>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('ui.incentive.kind') }}</th>
                        <th class="px-4 py-2">{{ __('ui.incentive.basis') }}</th>
                        <th class="px-4 py-2">{{ __('ui.incentive.settle') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.incentive.amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($entries as $e)
                        <tr>
                            <td class="px-4 py-2">{{ $e->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ __('ui.incentive.kind_'.$e->kind) }}</td>
                            <td class="px-4 py-2">
                                {{ __($basisKey[$e->basis] ?? 'ui.incentive.basis_fixed') }}
                                @if ($e->basis !== 'fixed')
                                    <span class="text-gray-400">({{ rtrim(rtrim(number_format($e->rate, 2), '0'), '.') }}%)</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ __('ui.incentive.settle_'.$e->settle_mode) }}</td>
                            <td class="px-4 py-2 text-right">@taka($e->amount)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
