@php
    $section = function ($title, $rows) {
        $html = "<h3 class='font-semibold text-gray-700 mb-2'>$title</h3>";
        foreach ($rows as $r) {
            $html .= "<div class='flex justify-between text-sm py-0.5'><span>".e($r['name'])."</span><span>".\App\Support\Money::taka($r['balance'])."</span></div>";
        }
        return $html;
    };
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.report.balance_sheet') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-4 text-sm {{ $balanced ? 'text-green-700' : 'text-red-700' }}">
            {{ $balanced ? __('ui.report.balanced') : __('ui.report.not_balanced') }}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                {!! $section(__('ui.report.assets'), $assets) !!}
                <div class="flex justify-between border-t mt-2 pt-2 font-semibold">
                    <span>{{ __('ui.report.total') }}</span><span>@taka($total_assets)</span>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 space-y-4">
                <div>
                    {!! $section(__('ui.report.liabilities'), $liabilities) !!}
                    <div class="flex justify-between border-t mt-2 pt-2 font-medium">
                        <span>{{ __('ui.report.total') }}</span><span>@taka($total_liabilities)</span>
                    </div>
                </div>
                <div>
                    {!! $section(__('ui.report.equity'), $equity) !!}
                    <div class="flex justify-between border-t mt-2 pt-2 font-medium">
                        <span>{{ __('ui.report.total') }}</span><span>@taka($total_equity)</span>
                    </div>
                </div>
                <div class="flex justify-between border-t-2 pt-2 font-semibold">
                    <span>{{ __('ui.report.liabilities') }} + {{ __('ui.report.equity') }}</span>
                    <span>@taka($total_liabilities + $total_equity)</span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
