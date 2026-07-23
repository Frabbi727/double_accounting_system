<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.day_book') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="GET" class="mb-4 flex items-end gap-3">
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.common.date') }}</label>
                <input type="date" name="date" value="{{ $date }}" class="mt-1 rounded border-gray-300 shadow-sm text-sm">
            </div>
            <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.report.go') }}</button>
        </form>

        @forelse ($entries as $entry)
            <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
                <div class="px-4 py-2 bg-gray-50 text-sm font-medium">{{ $entry['description'] }}</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($entry['lines'] as $line)
                            <tr>
                                <td class="px-4 py-1.5">{{ $line['account_code'] }} — {{ $line['account_name'] }}</td>
                                <td class="px-4 py-1.5 text-right">{{ $line['debit'] > 0 ? \App\Support\Money::taka($line['debit']) : '' }}</td>
                                <td class="px-4 py-1.5 text-right">{{ $line['credit'] > 0 ? \App\Support\Money::taka($line['credit']) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="bg-white rounded-lg shadow p-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</div>
        @endforelse
    </div>
</x-app-layout>
