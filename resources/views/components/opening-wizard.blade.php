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

        {{-- ============ Progress spine ============ --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-100 p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-700">
                    {{ __('ui.opening.wizard.step_counter', ['current' => $currentIndex + 1, 'total' => $totalSteps]) }}
                </span>
                <a href="{{ route('opening.index') }}"
                   class="text-xs text-indigo-600 hover:text-indigo-700 hover:underline">
                    {{ __('ui.opening.wizard.advanced_link') }}
                </a>
            </div>

            {{-- Segmented bar --}}
            <div class="flex gap-1.5" aria-hidden="true">
                @foreach ($steps as $s)
                    <div class="h-1.5 flex-1 rounded-full {{ $s['index'] <= $currentIndex ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
                @endforeach
            </div>

            {{-- Numbered checklist — one line, scrolls sideways on small screens --}}
            <div class="-mx-1 overflow-x-auto">
                <ol class="flex items-center gap-1 px-1 min-w-max">
                    @foreach ($steps as $s)
                        @php($state = $s['key'] === $current ? 'current' : ($s['done'] ? 'done' : 'todo'))
                        <li class="flex items-center">
                            <a href="{{ $s['url'] }}"
                               @class([
                                   'inline-flex items-center gap-2 rounded-full py-1 pl-1 pr-3 transition',
                                   'bg-indigo-50 text-indigo-700 font-semibold ring-1 ring-indigo-200' => $state === 'current',
                                   'text-green-700 hover:bg-gray-50' => $state === 'done',
                                   'text-gray-400 hover:bg-gray-50' => $state === 'todo',
                               ])>
                                <span @class([
                                    'inline-flex items-center justify-center w-6 h-6 rounded-full text-[11px] font-semibold shrink-0',
                                    'bg-indigo-600 text-white' => $state === 'current',
                                    'bg-green-100 text-green-700' => $state === 'done',
                                    'bg-gray-100 text-gray-400' => $state === 'todo',
                                ])>
                                    {{ $state === 'done' ? '✓' : $s['index'] + 1 }}
                                </span>
                                <span class="whitespace-nowrap text-xs">{{ $s['label'] }}</span>
                            </a>
                            @unless ($loop->last)
                                <span class="mx-0.5 text-gray-200" aria-hidden="true">›</span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- ============ Step body ============ --}}
        <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-100 p-6 sm:p-8 space-y-6">
            @if ($title)
                <div class="space-y-1">
                    <h3 class="text-xl font-semibold text-gray-900">{{ $title }}</h3>
                    @if ($help)
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $help }}</p>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </div>

        {{-- ============ Navigation footer ============ --}}
        <div class="flex items-center justify-between gap-4">
            <div>
                @if ($prevStep)
                    <a href="{{ $prevStep['url'] }}"
                       class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition">
                        <span aria-hidden="true">←</span> {{ __('ui.opening.wizard.back') }}
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-2">
                {{-- The footer only advances. Each step's own form saves data and
                     returns to the same step, so "next" is always safe. --}}
                @if ($nextStep)
                    @if ($current !== 'welcome')
                        <a href="{{ $nextStep['url'] }}"
                           class="rounded-md px-3 py-2 text-sm text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                            {{ __('ui.opening.wizard.skip') }}
                        </a>
                    @endif
                    <a href="{{ $nextStep['url'] }}"
                       class="inline-flex items-center gap-1.5 bg-indigo-600 text-white rounded-md px-5 py-2.5 text-sm font-medium shadow-sm hover:bg-indigo-700 transition">
                        {{ $current === 'welcome' ? __('ui.opening.wizard.start') : __('ui.opening.wizard.next') }}
                        <span aria-hidden="true">→</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
