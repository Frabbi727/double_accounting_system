<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.aging') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex gap-2 text-sm">
            <a href="{{ route('reports.aging', ['party' => 'customer']) }}"
               class="px-3 py-1.5 rounded {{ $party === 'customer' ? 'bg-gray-800 text-white' : 'bg-white shadow' }}">{{ __('ui.report.customer') }}</a>
            <a href="{{ route('reports.aging', ['party' => 'supplier']) }}"
               class="px-3 py-1.5 rounded {{ $party === 'supplier' ? 'bg-gray-800 text-white' : 'bg-white shadow' }}">{{ __('ui.report.supplier') }}</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.name') }}</th>
                        <th class="px-4 py-2 text-right">0-30</th>
                        <th class="px-4 py-2 text-right">31-60</th>
                        <th class="px-4 py-2 text-right">61-90</th>
                        <th class="px-4 py-2 text-right">90+</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['name'] }}</td>
                            @foreach (['0-30','31-60','61-90','90+'] as $b)
                                <td class="px-4 py-2 text-right">{{ $row['buckets'][$b] > 0 ? \App\Support\Money::taka($row['buckets'][$b]) : '' }}</td>
                            @endforeach
                            <td class="px-4 py-2 text-right font-medium">@taka($row['total'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ __('ui.report.total') }}</td>
                        @foreach (['0-30','31-60','61-90','90+'] as $b)
                            <td class="px-4 py-2 text-right">@taka($report['buckets'][$b])</td>
                        @endforeach
                        <td class="px-4 py-2 text-right">@taka($report['total'])</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
