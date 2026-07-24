<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.welcome_title')"
    :help="__('ui.opening.wizard.welcome_help')">

    <ul class="space-y-2.5">
        @foreach (['cash', 'suppliers', 'customers', 'products', 'assets'] as $i => $key)
            <li class="flex items-start gap-4 rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 text-white text-sm font-semibold shrink-0">
                    {{ $i + 1 }}
                </span>
                <div class="space-y-0.5">
                    <p class="font-medium text-gray-900">{{ __("ui.opening.wizard.step.$key") }}</p>
                    <p class="text-sm text-gray-500 leading-relaxed">{{ __("ui.opening.wizard.intro.$key") }}</p>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <span aria-hidden="true">💡</span>
        <span class="leading-relaxed">{{ __('ui.opening.wizard.welcome_note') }}</span>
    </div>
</x-opening-wizard>
