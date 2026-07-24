<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('asset.list_title') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('asset-categories.index') }}" class="text-sm text-gray-600 hover:underline px-3 py-2">{{ __('asset.categories.title') }}</a>
                <a href="{{ route('assets.create') }}" class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('asset.new') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        @include('shop._flash')

        <div class="bg-white rounded-lg shadow p-4 flex items-center justify-between">
            <span class="text-sm text-gray-500">{{ __('asset.active_total') }}</span>
            <span class="text-lg font-bold text-gray-900">@taka($activeTotal)</span>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('asset.asset_no') }}</th>
                        <th class="px-4 py-2">{{ __('asset.name') }}</th>
                        <th class="px-4 py-2">{{ __('asset.category') }}</th>
                        <th class="px-4 py-2">{{ __('asset.purchase_date') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('asset.amount') }}</th>
                        <th class="px-4 py-2">{{ __('asset.status') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($assets as $asset)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $asset->asset_no }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('assets.show', $asset) }}" class="text-indigo-600 hover:underline">{{ $asset->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $asset->category?->name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $asset->purchase_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right">@taka($asset->amount)</td>
                            <td class="px-4 py-2">
                                @if ($asset->disposed())
                                    <span class="text-xs rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">{{ __('asset.status_disposed') }}</span>
                                @else
                                    <span class="text-xs rounded-full bg-green-100 text-green-700 px-2 py-0.5">{{ __('asset.status_active') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('assets.show', $asset) }}" class="text-xs text-gray-500 hover:underline">{{ __('asset.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ __('asset.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $assets->links() }}</div>
    </div>
</x-app-layout>
