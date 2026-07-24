<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.cash_title')"
    :help="__('ui.opening.wizard.cash_help')">

    @if ($accounts->isEmpty() && $loans->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">
            {{ __('ui.opening.wizard.cash_none') }}
        </div>
    @else
        {{-- One amount row per account (partial `_account_row`). Cash/bank and loan
             rows share one submit — storeCash loops every amounts[id] by subtype. --}}
        <form method="POST" action="{{ route('opening.setup.cash') }}" class="space-y-5">
            @csrf

            {{-- Cash / bank: money you HAVE --}}
            @unless ($accounts->isEmpty())
                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                    <p class="text-sm font-semibold text-gray-700">{{ __('ui.opening.wizard.cash_section_title') }}</p>
                    <p class="text-xs text-gray-500 mb-3">{{ __('ui.opening.wizard.cash_section_help') }}</p>
                    <div class="divide-y divide-gray-100">
                        @foreach ($accounts as $row)
                            @include('shop.opening.setup._account_row', ['row' => $row])
                        @endforeach
                    </div>
                </section>
            @endunless

            {{-- Loans: money you OWE (borrowed) --}}
            @unless ($loans->isEmpty())
                <section class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5">
                    <p class="text-sm font-semibold text-gray-700">{{ __('ui.opening.wizard.loan_section_title') }}</p>
                    <p class="text-xs text-gray-500 mb-3">{{ __('ui.opening.wizard.loan_section_help') }}</p>
                    <div class="divide-y divide-gray-100">
                        @foreach ($loans as $row)
                            @include('shop.opening.setup._account_row', ['row' => $row])
                        @endforeach
                    </div>
                </section>
            @endunless

            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('accounts.create') }}" class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">
                    + {{ __('ui.opening.wizard.cash_add_account') }}
                </a>
                <button type="submit" class="inline-flex items-center gap-1.5 bg-gray-900 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-gray-800 transition">
                    {{ __('ui.opening.wizard.save') }}
                </button>
            </div>
        </form>
    @endif
</x-opening-wizard>
