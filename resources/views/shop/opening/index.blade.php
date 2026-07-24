<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.opening.title') }}</h2>
    </x-slot>

    @php($t = $summary['totals'])

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="{ confirming: false }">
        @include('shop._flash')

        {{-- ============ Mode banner ============ --}}
        @if ($locked)
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <span class="font-semibold">{{ __('ui.opening.mode_business') }}</span>
                — {{ __('ui.opening.mode_business_help') }}
            </div>
        @else
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <span class="font-semibold">{{ __('ui.opening.mode_setup') }}</span>
                — {{ __('ui.opening.mode_setup_help') }}
            </div>
        @endif

        {{-- ============ Overall summary ============ --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('ui.opening.overall') }}</h3>

            <div class="mb-4 text-sm font-medium {{ $t['balanced'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $t['balanced'] ? __('ui.report.balanced') : __('ui.report.not_balanced') }}
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_assets') }}</dt><dd class="font-medium">@taka($t['total_assets'])</dd></div>
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_liabilities') }}</dt><dd class="font-medium">@taka($t['total_liabilities'])</dd></div>
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_equity') }}</dt><dd class="font-medium">@taka($t['total_equity'])</dd></div>
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.inventory_value') }}</dt><dd class="font-medium">@taka($summary['inventory']['total_value'])</dd></div>
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_customer_due') }}</dt><dd class="font-medium">@taka($summary['customers']['total'])</dd></div>
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.total_supplier_due') }}</dt><dd class="font-medium">@taka($summary['suppliers']['total'])</dd></div>
                <div class="flex justify-between border-b pb-1"><dt class="text-gray-500">{{ __('ui.opening.opening_cash_position') }}</dt><dd class="font-medium">@taka($t['opening_cash'])</dd></div>
            </dl>
        </div>

        {{-- ============ Missing-information check ============ --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('ui.opening.checks') }}</h3>
            @if (count($summary['warnings']) === 0)
                <p class="text-sm text-green-700">{{ __('ui.opening.all_good') }}</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($summary['warnings'] as $w)
                        <li class="flex items-start gap-2 {{ $w['severity'] === 'blocker' ? 'text-red-700' : 'text-amber-700' }}">
                            <span>{{ $w['severity'] === 'blocker' ? '⛔' : '⚠️' }}</span>
                            <span>{{ __('ui.opening.warn.' . $w['key']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- ============ Category breakdowns ============ --}}
        @foreach (['assets', 'liabilities', 'equity'] as $section)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('ui.opening.' . $section) }}</h3>
                @php($groups = $summary['sections'][$section])
                @if (count($groups) === 0)
                    <p class="text-sm text-gray-400">{{ __('ui.opening.no_rows') }}</p>
                @else
                    @foreach ($groups as $subtype => $rows)
                        <div class="mb-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                {{ __('ui.opening.subtype.' . $subtype) }}
                            </p>
                            <dl class="text-sm">
                                @foreach ($rows as $row)
                                    <div class="flex justify-between border-t pt-1 mt-1">
                                        <dt class="text-gray-600">{{ $row['name'] }} <span class="text-gray-400 text-xs">({{ $row['code'] }})</span></dt>
                                        <dd>@taka($row['balance'])</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach

        {{-- ============ Accounts ============ --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                {{ __('ui.opening.accounts') }}
                <span class="text-gray-400 font-normal">— {{ __('ui.opening.account_count') }}: {{ $summary['accounts']['count'] }}</span>
            </h3>
            @if ($summary['accounts']['count'] === 0)
                <p class="text-sm text-gray-400">{{ __('ui.opening.no_rows') }}</p>
            @else
                <dl class="text-sm">
                    @foreach ($summary['accounts']['rows'] as $row)
                        <div class="flex justify-between border-t pt-1 mt-1">
                            <dt class="text-gray-600">{{ $row['name'] }} <span class="text-gray-400 text-xs">({{ __('ui.opening.subtype.' . $row['subtype']) }})</span></dt>
                            <dd>@taka($row['balance'])</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>

        {{-- ============ Customers / Suppliers / Inventory ============ --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('ui.opening.customers') }}</h3>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.opening.customer_count') }}</dt><dd>{{ $summary['customers']['count'] }}</dd></div>
                    <div class="flex justify-between border-t pt-1 font-semibold"><dt>{{ __('ui.opening.total_customer_due') }}</dt><dd>@taka($summary['customers']['total'])</dd></div>
                </dl>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('ui.opening.suppliers') }}</h3>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.opening.supplier_count') }}</dt><dd>{{ $summary['suppliers']['count'] }}</dd></div>
                    <div class="flex justify-between border-t pt-1 font-semibold"><dt>{{ __('ui.opening.total_supplier_due') }}</dt><dd>@taka($summary['suppliers']['total'])</dd></div>
                </dl>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('ui.opening.inventory') }}</h3>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('ui.opening.total_products') }}</dt><dd>{{ $summary['inventory']['product_count'] }}</dd></div>
                    <div class="flex justify-between border-t pt-1 font-semibold"><dt>{{ __('ui.opening.opening_stock_value') }}</dt><dd>@taka($summary['inventory']['total_value'])</dd></div>
                </dl>
            </div>
        </div>

        {{-- ============ Lock action / audit ============ --}}
        <div class="bg-white rounded-lg shadow p-6">
            @if ($locked)
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('ui.opening.audit') }}</h3>
                <p class="text-green-700 text-sm mb-4">{{ __('ui.opening.locked_note') }}</p>
                <dl class="text-sm space-y-1">
                    @if ($lockedBy)
                        <div class="flex justify-between border-t pt-1"><dt class="text-gray-500">{{ __('ui.opening.locked_by') }}</dt><dd>{{ $lockedBy }}</dd></div>
                    @endif
                    @if ($lockedAt)
                        <div class="flex justify-between border-t pt-1"><dt class="text-gray-500">{{ __('ui.opening.locked_at') }}</dt><dd>{{ $lockedAt->format('Y-m-d H:i') }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t pt-1"><dt class="text-gray-500">{{ __('ui.opening.generated_entries') }}</dt><dd>{{ $openingEntries->count() }} {{ __('ui.opening.entries_count') }}</dd></div>
                </dl>

                @if ($openingEntries->count() > 0)
                    <div class="mt-4 border-t pt-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ __('ui.opening.generated_entries') }}</p>
                        <ul class="text-sm divide-y">
                            @foreach ($openingEntries as $entry)
                                <li class="flex justify-between py-1">
                                    <span class="text-gray-600">{{ $entry->description }}</span>
                                    <span class="text-gray-500">{{ $entry->date->format('Y-m-d') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Primary recovery: go back to setup to add/fix opening data. --}}
                <div class="mt-6 border-t pt-4">
                    <p class="text-xs text-gray-500 mb-3">{{ __('ui.opening.back_to_setup_help') }}</p>
                    <form method="POST" action="{{ route('opening.unlock') }}">
                        @csrf
                        <button type="submit" class="bg-amber-600 text-white rounded px-4 py-2 text-sm hover:bg-amber-700">
                            {{ __('ui.opening.back_to_setup') }}
                        </button>
                    </form>
                </div>

                {{-- Owner recovery: fix the business start date without a developer. --}}
                <div class="mt-6 border-t pt-4" x-data="{ editing: false }">
                    <button type="button" @click="editing = !editing"
                            class="text-sm text-indigo-600 hover:underline">
                        {{ __('ui.opening.change_start_date') }}
                    </button>
                    <div x-show="editing" x-cloak style="display:none" class="mt-3">
                        <p class="text-xs text-gray-500 mb-3">{{ __('ui.opening.change_start_date_help') }}</p>
                        <form method="POST" action="{{ route('opening.reopen') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="text-sm font-medium text-gray-700">{{ __('ui.opening.start_date_label') }}</label>
                                <input name="start_date" type="date" required
                                       value="{{ old('start_date', now()->toDateString()) }}"
                                       class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="text-sm text-gray-600">{{ __('ui.opening.change_start_date_reason') }}</label>
                                <input name="reason" value="{{ old('reason') }}"
                                       class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
                            </div>
                            <button type="submit" class="bg-gray-800 text-white rounded px-4 py-2 text-sm hover:bg-gray-700">
                                {{ __('ui.opening.change_start_date_btn') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-gray-600 text-sm mb-4">{{ __('ui.opening.unlocked_note') }}</p>
                <button type="button"
                        @click="confirming = true"
                        @disabled($summary['has_blocker'])
                        class="bg-gray-800 text-white rounded px-4 py-2 text-sm hover:bg-gray-700 {{ $summary['has_blocker'] ? 'opacity-50 cursor-not-allowed' : '' }}">
                    {{ __('ui.opening.review_lock') }}
                </button>

                @unless ($summary['has_blocker'])
                    @include('shop.opening._confirm')
                @endunless
            @endif
        </div>
    </div>
</x-app-layout>
