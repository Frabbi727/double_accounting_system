@props([
    'current',
    'steps',
    'currentIndex',
    'totalSteps',
    'prevStep' => null,
    'nextStep' => null,
    'title' => null,
    'help' => null,
])

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.opening.wizard.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')

        {{-- ============ Progress ============ --}}
        <div class="bg-white rounded-lg shadow p-5 space-y-4">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold text-gray-700">
                    {{ __('ui.opening.wizard.step_counter', ['current' => $currentIndex + 1, 'total' => $totalSteps]) }}
                </span>
                <a href="{{ route('opening.index') }}" class="text-xs text-indigo-600 hover:underline">
                    {{ __('ui.opening.wizard.advanced_link') }}
                </a>
            </div>

            {{-- Segmented bar --}}
            <div class="flex gap-1">
                @foreach ($steps as $s)
                    <div class="h-2 flex-1 rounded-full {{ $s['index'] <= $currentIndex ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
                @endforeach
            </div>

            {{-- Numbered checklist --}}
            <ol class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
                @foreach ($steps as $s)
                    <li>
                        <a href="{{ $s['url'] }}"
                           class="inline-flex items-center gap-1.5 {{ $s['key'] === $current ? 'text-indigo-700 font-semibold' : ($s['done'] ? 'text-green-700' : 'text-gray-400') }}">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border text-[10px]
                                {{ $s['key'] === $current ? 'border-indigo-600 bg-indigo-50' : ($s['done'] ? 'border-green-600 bg-green-50' : 'border-gray-300') }}">
                                {{ $s['done'] && $s['key'] !== $current ? '✓' : $s['index'] + 1 }}
                            </span>
                            <span class="whitespace-nowrap">{{ $s['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- ============ Step body ============ --}}
        <div class="bg-white rounded-lg shadow p-6 space-y-5">
            @if ($title)
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">{{ $title }}</h3>
                    @if ($help)
                        <p class="mt-1 text-sm text-gray-500 leading-relaxed">{{ $help }}</p>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </div>

        {{-- ============ Navigation ============ --}}
        <div class="flex items-center justify-between">
            <div>
                @if ($prevStep)
                    <a href="{{ $prevStep['url'] }}" class="text-sm text-gray-500 hover:text-gray-700">
                        ← {{ __('ui.opening.wizard.back') }}
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-4">
                {{-- The nav footer only advances. Each step's own form saves data
                     and returns to the same step, so "next" is always safe. --}}
                @isset($footer)
                    {{ $footer }}
                @elseif ($nextStep)
                    @if ($current !== 'welcome' && $nextStep)
                        <a href="{{ $nextStep['url'] }}" class="text-sm text-gray-400 hover:text-gray-600">
                            {{ __('ui.opening.wizard.skip') }}
                        </a>
                    @endif
                    <a href="{{ $nextStep['url'] }}"
                       class="bg-indigo-600 text-white rounded px-5 py-2 text-sm font-medium hover:bg-indigo-700">
                        {{ $current === 'welcome' ? __('ui.opening.wizard.start') : __('ui.opening.wizard.next') }} →
                    </a>
                @endisset
            </div>
        </div>
    </div>
</x-app-layout>
