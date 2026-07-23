<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ $title }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.name') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.customer.due') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('reports.party_statement', ['party' => $party, 'id' => $r['id']]) }}"
                                   class="text-indigo-600 hover:underline">{{ $r['name'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-right">@taka($r['due'])</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('reports.party_statement', ['party' => $party, 'id' => $r['id']]) }}"
                                   class="text-gray-500 hover:underline">{{ __('ui.report.details') }}</a>
                                @can('payment.manage')
                                    <a href="{{ route('payments.create', ['direction' => $direction, 'party_id' => $r['id']]) }}"
                                       class="ml-3 inline-block bg-gray-800 text-white rounded px-3 py-1 text-xs">{{ __('ui.report.settle') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ __('ui.report.total') }}</td>
                        <td class="px-4 py-2 text-right">@taka(array_sum(array_column($rows, 'due')))</td>
                        <td class="px-4 py-2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
