<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.purchase.title') }}</h2>
            <a href="{{ route('purchases.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.purchase.new') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.common.date') }}</th>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.report.total') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.purchase.paid') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($purchases as $p)
                        <tr>
                            <td class="px-4 py-2">{{ $p->date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $p->invoice_no ?? '#'.$p->id }}</td>
                            <td class="px-4 py-2 text-right">@taka($p->total())</td>
                            <td class="px-4 py-2 text-right">@taka($p->paid_amount)</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('purchases.print', $p) }}" target="_blank" class="text-blue-600 hover:underline">{{ __('ui.bill.print') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
