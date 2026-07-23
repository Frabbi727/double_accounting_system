<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.trial_balance') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-4 text-sm {{ $balanced ? 'text-green-700' : 'text-red-700' }}">
            {{ $balanced ? __('ui.report.balanced') : __('ui.report.not_balanced') }}
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.report.code') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.account') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.debit') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.credit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-4 py-2">{{ $r['code'] }}</td>
                            <td class="px-4 py-2">{{ $r['name'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $r['debit'] > 0 ? \App\Support\Money::taka($r['debit']) : '' }}</td>
                            <td class="px-4 py-2 text-right">{{ $r['credit'] > 0 ? \App\Support\Money::taka($r['credit']) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2" colspan="2">{{ __('ui.report.total') }}</td>
                        <td class="px-4 py-2 text-right">@taka($total_debit)</td>
                        <td class="px-4 py-2 text-right">@taka($total_credit)</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
