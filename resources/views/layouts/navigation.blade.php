@php
    $locale = app()->getLocale();
    $linkBase = 'group flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors';
    $linkIdle = 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';
    $linkActive = 'bg-indigo-50 text-indigo-700 font-medium';
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen"
     x-cloak
     x-transition.opacity
     @click="sidebarOpen = false"
     class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

{{-- Sidebar --}}
<aside x-cloak
       :class="[
           collapsed ? 'lg:w-16' : 'lg:w-64',
           sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
       ]"
       class="fixed inset-y-0 start-0 z-40 w-64 flex flex-col bg-white border-e border-gray-200 overflow-y-auto transition-all duration-200">

    {{-- Header: brand + toggles --}}
    <div class="flex items-center justify-between h-16 px-4 shrink-0 border-b border-gray-200">
        <a href="{{ route('dashboard') }}"
           class="flex items-center font-semibold text-gray-800 whitespace-nowrap overflow-hidden">
            <span x-show="!collapsed">{{ __('ui.app_name') }}</span>
            <span x-show="collapsed" class="hidden lg:inline w-full text-center">{{ mb_substr(__('ui.app_name'), 0, 1) }}</span>
        </a>

        {{-- Desktop collapse toggle --}}
        <button type="button"
                @click="collapsed = !collapsed"
                class="hidden lg:inline-flex items-center justify-center p-1.5 rounded text-gray-500 hover:bg-gray-100"
                :title="collapsed ? '{{ __('ui.nav.expand') }}' : '{{ __('ui.nav.collapse') }}'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path x-show="!collapsed" stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                <path x-show="collapsed" stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
            </svg>
        </button>

        {{-- Mobile close --}}
        <button type="button"
                @click="sidebarOpen = false"
                class="lg:hidden inline-flex items-center justify-center p-1.5 rounded text-gray-500 hover:bg-gray-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Scrollable nav links --}}
    <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-6">

        {{-- মূল --}}
        <div class="space-y-1">
            <a href="{{ route('dashboard') }}"
               class="{{ $linkBase }} {{ request()->routeIs('dashboard') ? $linkActive : $linkIdle }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
                <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.dashboard') }}</span>
            </a>
        </div>

        {{-- মাস্টার ডেটা --}}
        @canany(['master.manage', 'opening.manage'])
            <div class="space-y-1">
                <p x-show="!collapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('ui.nav_group.master') }}</p>

                @can('opening.manage')
                    <a href="{{ route('opening.index') }}" class="{{ $linkBase }} {{ request()->routeIs('opening.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.opening') }}</span>
                    </a>
                @endcan

                @can('master.manage')
                    <a href="{{ route('products.index') }}" class="{{ $linkBase }} {{ request()->routeIs('products.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-14L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.products') }}</span>
                    </a>
                    <a href="{{ route('customers.index') }}" class="{{ $linkBase }} {{ request()->routeIs('customers.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.customers') }}</span>
                    </a>
                    <a href="{{ route('suppliers.index') }}" class="{{ $linkBase }} {{ request()->routeIs('suppliers.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h11m0 0l4 4m-4-4l4-4m3 8v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.suppliers') }}</span>
                    </a>
                    <a href="{{ route('accounts.index') }}" class="{{ $linkBase }} {{ request()->routeIs('accounts.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.accounts') }}</span>
                    </a>
                @endcan
            </div>
        @endcanany

        {{-- লেনদেন --}}
        @canany(['sale.create', 'purchase.create', 'expense.create', 'payment.manage', 'entry.delete'])
            <div class="space-y-1">
                <p x-show="!collapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('ui.nav_group.transaction') }}</p>

                @can('sale.create')
                    <a href="{{ route('sales.index') }}" class="{{ $linkBase }} {{ request()->routeIs('sales.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3M17 13l1.3 2.3M9 20a1 1 0 11-2 0 1 1 0 012 0zm8 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.sales') }}</span>
                    </a>
                @endcan

                @can('purchase.create')
                    <a href="{{ route('purchases.index') }}" class="{{ $linkBase }} {{ request()->routeIs('purchases.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4m16 0l-1 12a2 2 0 01-2 2H7a2 2 0 01-2-2L4 7m5 0V5a2 2 0 012-2h2a2 2 0 012 2v2"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.purchases') }}</span>
                    </a>
                @endcan

                @can('expense.create')
                    <a href="{{ route('expenses.index') }}" class="{{ $linkBase }} {{ request()->routeIs('expenses.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m0-8a3 3 0 013 3M9 13a3 3 0 003 3"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav_more.expense') }}</span>
                    </a>
                @endcan

                @can('payment.manage')
                    <a href="{{ route('payments.index') }}" class="{{ $linkBase }} {{ request()->routeIs('payments.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav_more.payment') }}</span>
                    </a>
                    <a href="{{ route('transfers.create') }}" class="{{ $linkBase }} {{ request()->routeIs('transfers.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav_more.transfer') }}</span>
                    </a>
                    <a href="{{ route('incentives.index') }}" class="{{ $linkBase }} {{ request()->routeIs('incentives.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav_more.incentive') }}</span>
                    </a>
                @endcan

                @can('entry.delete')
                    <a href="{{ route('returns.sale') }}" class="{{ $linkBase }} {{ request()->routeIs('returns.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4m-4 4l4 4"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav_more.returns') }}</span>
                    </a>
                    <a href="{{ route('rebates.index') }}" class="{{ $linkBase }} {{ request()->routeIs('rebates.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav_more.rebate') }}</span>
                    </a>
                @endcan
            </div>
        @endcanany

        {{-- রিপোর্ট --}}
        @can('report.view')
            <div class="space-y-1">
                <p x-show="!collapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('ui.nav_group.report') }}</p>
                <a href="{{ route('reports.index') }}" class="{{ $linkBase }} {{ request()->routeIs('reports.*') ? $linkActive : $linkIdle }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-4M4 4v16h16"/></svg>
                    <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.reports') }}</span>
                </a>
            </div>
        @endcan

        {{-- সেটিংস --}}
        @canany(['master.manage', 'user.manage', 'backup.manage'])
            <div class="space-y-1">
                <p x-show="!collapsed" class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('ui.nav_group.settings') }}</p>

                @can('master.manage')
                    <a href="{{ route('shop-profile.edit') }}" class="{{ $linkBase }} {{ request()->routeIs('shop-profile.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-2a4 4 0 014-4h4m6 5v-5m0 0l-2 2m2-2l2 2M9 7a3 3 0 106 0 3 3 0 00-6 0z"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.shop_profile.title') }}</span>
                    </a>
                @endcan

                @can('user.manage')
                    <a href="{{ route('users.index') }}" class="{{ $linkBase }} {{ request()->routeIs('users.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.user.title') }}</span>
                    </a>
                @endcan

                @can('backup.manage')
                    <a href="{{ route('backup.index') }}" class="{{ $linkBase }} {{ request()->routeIs('backup.*') ? $linkActive : $linkIdle }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 1.657 3.582 3 8 3s8-1.343 8-3V7M4 7c0 1.657 3.582 3 8 3s8-1.343 8-3M4 7c0-1.657 3.582-3 8-3s8 1.343 8 3"/></svg>
                        <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.backup.title') }}</span>
                    </a>
                @endcan
            </div>
        @endcanany
    </nav>

    {{-- Bottom: locale + user + logout --}}
    <div class="shrink-0 border-t border-gray-200 p-3 space-y-2">
        <div x-show="!collapsed" class="flex items-center justify-between text-sm">
            <span class="text-gray-400">
                <a href="{{ route('locale.switch', 'bn') }}" class="{{ $locale === 'bn' ? 'font-bold text-gray-900' : 'hover:text-gray-700' }}">বাংলা</a>
                /
                <a href="{{ route('locale.switch', 'en') }}" class="{{ $locale === 'en' ? 'font-bold text-gray-900' : 'hover:text-gray-700' }}">EN</a>
            </span>
        </div>

        <div class="flex items-center gap-2" :class="collapsed ? 'lg:justify-center' : 'justify-between'">
            <span x-show="!collapsed" class="text-sm text-gray-600 truncate">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-red-600"
                        :title="collapsed ? '{{ __('ui.nav.logout') }}' : ''">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span x-show="!collapsed" class="whitespace-nowrap">{{ __('ui.nav.logout') }}</span>
                </button>
            </form>
        </div>
    </div>
</aside>
