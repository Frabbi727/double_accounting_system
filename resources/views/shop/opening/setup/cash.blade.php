<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.cash_title')"
    :help="__('ui.opening.wizard.cash_help')">

    @if ($accounts->isEmpty() && $loans->isEmpty())
        <p class="text-sm text-gray-500">{{ __('ui.opening.wizard.cash_none') }}</p>
    @else
        {{-- Cash/bank and loan rows share one submit — storeCash loops every
             amounts[id] regardless of account subtype. --}}
        <form method="POST" action="{{ route('opening.setup.cash') }}" class="space-y-5">
            @csrf

            {{-- Cash / bank: money you HAVE --}}
            @unless ($accounts->isEmpty())
                <div class="divide-y">
                    @foreach ($accounts as $row)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $row['model']->name }}</p>
                                <p class="text-xs text-gray-400">{{ __('ui.opening.subtype.' . $row['model']->subtype) }}</p>
                            </div>
                            <div class="w-40">
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 text-sm">৳</span>
                                    <input type="number" step="0.01" min="0"
                                           name="amounts[{{ $row['model']->id }}]"
                                           value="{{ $row['balance'] > 0 ? $row['balance'] : '' }}"
                                           placeholder="0"
                                           class="block w-full rounded border-gray-300 shadow-sm text-sm pl-7 text-right">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endunless

            {{-- Loans: money you OWE (borrowed) --}}
            @unless ($loans->isEmpty())
                <div class="border-t pt-4">
                    <p class="text-sm font-semibold text-gray-700">{{ __('ui.opening.wizard.loan_section_title') }}</p>
                    <p class="text-xs text-gray-500 mb-2">{{ __('ui.opening.wizard.loan_section_help') }}</p>
                    <div class="divide-y">
                        @foreach ($loans as $row)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $row['model']->name }}</p>
                                    <p class="text-xs text-gray-400">{{ __('ui.opening.subtype.loan') }}</p>
                                </div>
                                <div class="w-40">
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 text-sm">৳</span>
                                        <input type="number" step="0.01" min="0"
                                               name="amounts[{{ $row['model']->id }}]"
                                               value="{{ $row['balance'] > 0 ? $row['balance'] : '' }}"
                                               placeholder="0"
                                               class="block w-full rounded border-gray-300 shadow-sm text-sm pl-7 text-right">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endunless

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('accounts.create') }}" class="text-sm text-indigo-600 hover:underline">
                    + {{ __('ui.opening.wizard.cash_add_account') }}
                </a>
                <button type="submit" class="bg-gray-800 text-white rounded px-4 py-2 text-sm hover:bg-gray-700">
                    {{ __('ui.opening.wizard.save') }}
                </button>
            </div>
        </form>
    @endif
</x-opening-wizard>
