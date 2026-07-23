<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">
                {{ __('ui.report.account_statement') }} — {{ $report['account']->code }} {{ $report['account']->name }}
            </h2>
            <a href="{{ route('accounts.index') }}" class="text-sm text-gray-500">{{ __('ui.common.cancel') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')

        <form method="GET" class="mb-4 flex items-end gap-3 flex-wrap">
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.report.from') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="mt-1 rounded border-gray-300 shadow-sm text-sm">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.report.to') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="mt-1 rounded border-gray-300 shadow-sm text-sm">
            </div>
            <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.report.go') }}</button>
            @can('report.view')
                <div class="flex gap-2 text-sm ml-auto">
                    <a href="{{ route('reports.export.account_statement', ['account' => $report['account'], 'from' => $from, 'to' => $to, 'format' => 'csv']) }}"
                       class="bg-white shadow rounded px-3 py-2">{{ __('ui.report.export_csv') }}</a>
                    <a href="{{ route('reports.export.account_statement', ['account' => $report['account'], 'from' => $from, 'to' => $to, 'format' => 'pdf']) }}"
                       class="bg-white shadow rounded px-3 py-2">{{ __('ui.report.export_pdf') }}</a>
                </div>
            @endcan
        </form>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.type') }}</th>
                        <th class="px-4 py-2">{{ __('ui.report.description') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.in') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.out') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr class="bg-gray-50">
                        <td class="px-4 py-2" colspan="5">{{ __('ui.report.opening') }}</td>
                        <td class="px-4 py-2 text-right font-medium">@taka($report['opening'])</td>
                    </tr>
                    @forelse ($report['rows'] as $r)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($r['date'])->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-block rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $r['type_label'] }}</span>
                            </td>
                            <td class="px-4 py-2">{{ $r['description'] }}</td>
                            <td class="px-4 py-2 text-right text-green-700">{{ $r['in'] > 0 ? \App\Support\Money::taka($r['in']) : '' }}</td>
                            <td class="px-4 py-2 text-right text-red-700">{{ $r['out'] > 0 ? \App\Support\Money::taka($r['out']) : '' }}</td>
                            <td class="px-4 py-2 text-right">@taka($r['balance'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2" colspan="3">{{ __('ui.report.total_in') }} / {{ __('ui.report.total_out') }}</td>
                        <td class="px-4 py-2 text-right text-green-700">@taka($report['total_in'])</td>
                        <td class="px-4 py-2 text-right text-red-700">@taka($report['total_out'])</td>
                        <td class="px-4 py-2"></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2" colspan="5">{{ __('ui.report.closing') }}</td>
                        <td class="px-4 py-2 text-right">@taka($report['closing'])</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
