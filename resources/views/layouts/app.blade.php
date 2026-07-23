<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>[x-cloak]{ display: none !important; }</style>
    </head>
    <body class="font-sans antialiased">
        <div x-data="{
                sidebarOpen: false,
                collapsed: localStorage.getItem('sidebarCollapsed') === '1',
                init() { this.$watch('collapsed', v => localStorage.setItem('sidebarCollapsed', v ? '1' : '0')) }
             }"
             class="min-h-screen bg-gray-100">

            @include('layouts.navigation')

            {{-- Content, offset by sidebar width on desktop --}}
            <div :class="collapsed ? 'lg:ps-16' : 'lg:ps-64'" class="transition-all duration-200">

                {{-- Mobile top bar --}}
                <div class="lg:hidden sticky top-0 z-20 flex items-center gap-3 h-14 px-4 bg-white border-b border-gray-200">
                    <button type="button"
                            @click="sidebarOpen = true"
                            class="inline-flex items-center justify-center p-1.5 rounded text-gray-600 hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <a href="{{ route('dashboard') }}" class="font-semibold text-gray-800">{{ __('ui.app_name') }}</a>
                </div>

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
