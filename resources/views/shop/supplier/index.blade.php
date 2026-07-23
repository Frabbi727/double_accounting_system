<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.supplier.title') }}</h2>
            <a href="{{ route('suppliers.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.supplier.add') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.supplier.name') }}</th>
                        <th class="px-4 py-2">{{ __('ui.supplier.phone') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.supplier.due') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($suppliers as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="{{ route('suppliers.show', $s) }}" class="text-indigo-600 hover:underline">{{ $s->name }}</a>
                            </td>
                            <td class="px-4 py-2">{{ $s->phone }}</td>
                            <td class="px-4 py-2 text-right">@taka($dues[$s->id] ?? 0)</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('suppliers.show', $s) }}" class="text-sm text-indigo-600 hover:underline">{{ __('ui.report.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
