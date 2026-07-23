<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.profit_loss') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow p-6 space-y-4 text-sm">
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">{{ __('ui.report.income') }}</h3>
                @foreach ($income as $r)
                    <div class="flex justify-between"><span>{{ $r['name'] }}</span><span>@taka($r['balance'])</span></div>
                @endforeach
                <div class="flex justify-between border-t pt-1 font-medium"><span>{{ __('ui.report.total') }}</span><span>@taka($total_income)</span></div>
            </div>

            <div>
                <h3 class="font-semibold text-gray-700 mb-2">{{ __('ui.report.expense') }}</h3>
                @foreach ($expenses as $r)
                    <div class="flex justify-between"><span>{{ $r['name'] }}</span><span>@taka($r['balance'])</span></div>
                @endforeach
                <div class="flex justify-between border-t pt-1 font-medium"><span>{{ __('ui.report.total') }}</span><span>@taka($total_expense)</span></div>
            </div>

            <div class="flex justify-between border-t-2 pt-3 text-base font-semibold">
                <span>{{ __('ui.report.net_profit') }}</span><span>@taka($net_profit)</span>
            </div>
        </div>
    </div>
</x-app-layout>
