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
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-4 py-2">{{ $r['name'] }}</td>
                            <td class="px-4 py-2 text-right">@taka($r['due'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ __('ui.report.total') }}</td>
                        <td class="px-4 py-2 text-right">@taka($rows->sum('due'))</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
