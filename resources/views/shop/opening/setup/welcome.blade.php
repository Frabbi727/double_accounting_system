<x-opening-wizard
    :current="$current" :steps="$steps" :current-index="$currentIndex" :total-steps="$totalSteps"
    :prev-step="$prevStep" :next-step="$nextStep"
    :title="__('ui.opening.wizard.welcome_title')"
    :help="__('ui.opening.wizard.welcome_help')">

    <ul class="space-y-3 text-sm text-gray-700">
        @foreach (['cash', 'suppliers', 'customers', 'products', 'assets'] as $i => $key)
            <li class="flex items-start gap-3">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold shrink-0">
                    {{ $i + 1 }}
                </span>
                <div>
                    <p class="font-medium text-gray-800">{{ __("ui.opening.wizard.step.$key") }}</p>
                    <p class="text-gray-500">{{ __("ui.opening.wizard.intro.$key") }}</p>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {{ __('ui.opening.wizard.welcome_note') }}
    </div>
</x-opening-wizard>
