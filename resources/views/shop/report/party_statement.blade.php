<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.party_statement') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex gap-2 text-sm">
            <a href="{{ route('reports.party_statement', ['party' => 'customer']) }}"
               class="px-3 py-1.5 rounded {{ $party === 'customer' ? 'bg-gray-800 text-white' : 'bg-white shadow' }}">{{ __('ui.report.customer') }}</a>
            <a href="{{ route('reports.party_statement', ['party' => 'supplier']) }}"
               class="px-3 py-1.5 rounded {{ $party === 'supplier' ? 'bg-gray-800 text-white' : 'bg-white shadow' }}">{{ __('ui.report.supplier') }}</a>
        </div>

        <form method="GET" action="{{ route('reports.party_statement') }}" class="mb-4 flex flex-wrap items-end gap-3 bg-white rounded-lg shadow p-4">
            <input type="hidden" name="party" value="{{ $party }}">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ __('ui.report.select_party') }}</label>
                <select name="id" onchange="this.form.submit()" class="border-gray-300 rounded text-sm">
                    <option value="">— {{ __('ui.report.select_party') }} —</option>
                    @foreach ($parties as $p)
                        <option value="{{ $p->id }}" @selected($selectedId === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-gray-800 text-white rounded px-4 py-1.5 text-sm">{{ __('ui.report.go') }}</button>
        </form>

        @if ($statement)
            @can('payment.manage')
                <div class="mb-4 flex items-center justify-between bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-600">
                        {{ $statement['record']->name }} — <span class="font-semibold">@taka($statement['closing'])</span>
                    </div>
                    <a href="{{ route('payments.create', ['direction' => $party === 'supplier' ? 'made' : 'received', 'party_id' => $selectedId]) }}"
                       class="bg-gray-800 text-white rounded px-4 py-1.5 text-sm">{{ __('ui.report.settle') }}</a>
                </div>
            @endcan

            @include('shop._party_statement', ['linkable' => true])
        @endif
    </div>
</x-app-layout>
