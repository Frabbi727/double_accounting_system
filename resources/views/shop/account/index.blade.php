<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.account.title') }}</h2>
            <a href="{{ route('accounts.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.account.add') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.report.code') }}</th>
                        <th class="px-4 py-2">{{ __('ui.account.name') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.account.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($accounts as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['model']->code }}</td>
                            <td class="px-4 py-2">{{ $row['model']->name }}</td>
                            <td class="px-4 py-2 text-right">@taka($row['balance'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
