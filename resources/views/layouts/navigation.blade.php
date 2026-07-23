@php($locale = app()->getLocale())
<nav x-data="{ open: false }" class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <a href="{{ route('dashboard') }}" class="flex items-center font-semibold text-gray-800">
                    {{ __('ui.app_name') }}
                </a>

                <div class="hidden sm:flex sm:items-center sm:ms-8 sm:space-x-4 rtl:space-x-reverse text-sm">
                    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.dashboard') }}</a>

                    @can('opening.manage')
                        <a href="{{ route('opening.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.opening') }}</a>
                    @endcan

                    @can('master.manage')
                        <a href="{{ route('products.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.products') }}</a>
                        <a href="{{ route('customers.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.customers') }}</a>
                        <a href="{{ route('suppliers.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.suppliers') }}</a>
                        <a href="{{ route('accounts.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.accounts') }}</a>
                    @endcan

                    @can('sale.create')
                        <a href="{{ route('sales.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.sales') }}</a>
                    @endcan

                    @can('purchase.create')
                        <a href="{{ route('purchases.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.purchases') }}</a>
                    @endcan

                    @can('expense.create')
                        <a href="{{ route('expenses.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav_more.expense') }}</a>
                    @endcan

                    @can('payment.manage')
                        <a href="{{ route('payments.create') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav_more.payment') }}</a>
                        <a href="{{ route('transfers.create') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav_more.transfer') }}</a>
                        <a href="{{ route('incentives.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav_more.incentive') }}</a>
                    @endcan

                    @can('entry.delete')
                        <a href="{{ route('returns.sale') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav_more.returns') }}</a>
                        <a href="{{ route('rebates.create') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav_more.rebate') }}</a>
                    @endcan

                    @can('report.view')
                        <a href="{{ route('reports.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.reports') }}</a>
                    @endcan

                    @can('user.manage')
                        <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.user.title') }}</a>
                    @endcan
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:space-x-4 rtl:space-x-reverse text-sm">
                {{-- Language toggle --}}
                <span class="text-gray-400">
                    <a href="{{ route('locale.switch', 'bn') }}" class="{{ $locale === 'bn' ? 'font-bold text-gray-900' : 'hover:text-gray-700' }}">বাংলা</a>
                    /
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ $locale === 'en' ? 'font-bold text-gray-900' : 'hover:text-gray-700' }}">EN</a>
                </span>

                <span class="text-gray-600">{{ Auth::user()->name }}</span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-600 hover:text-gray-900">{{ __('ui.nav.logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</nav>
