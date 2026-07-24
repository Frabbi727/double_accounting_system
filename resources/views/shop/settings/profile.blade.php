<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.shop_profile.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('shop-profile.update') }}" enctype="multipart/form-data"
              class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.shop_profile.name') }}</label>
                <input name="name" value="{{ old('name', $name) }}" required class="{{ $input }}">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.shop_profile.address') }}</label>
                <textarea name="address" rows="2" class="{{ $input }}">{{ old('address', $address) }}</textarea>
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.shop_profile.phone') }}</label>
                <input name="phone" value="{{ old('phone', $phone) }}" class="{{ $input }}">
            </div>

            <div>
                <label class="text-sm text-gray-600">{{ __('ui.shop_profile.logo') }}</label>
                @if ($logoUrl)
                    <div class="mt-1 flex items-center gap-3">
                        <img src="{{ $logoUrl }}" alt="{{ __('ui.shop_profile.current_logo') }}" class="h-14 w-auto rounded border">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300">
                            {{ __('ui.shop_profile.remove_logo') }}
                        </label>
                    </div>
                @endif
                <input name="logo" type="file" accept="image/*" class="{{ $input }}">
                <p class="text-xs text-gray-400 mt-1">{{ __('ui.shop_profile.logo_hint') }}</p>
                @error('logo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="border-t pt-4">
                <label class="text-sm text-gray-600">{{ __('return.policy_label') }}</label>
                <select name="return_discount_policy" class="{{ $input }}">
                    <option value="ignore" @selected(old('return_discount_policy', $returnDiscountPolicy) === 'ignore')>{{ __('return.policy_ignore') }}</option>
                    <option value="proportional" @selected(old('return_discount_policy', $returnDiscountPolicy) === 'proportional')>{{ __('return.policy_proportional') }}</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">{{ __('return.policy_hint') }}</p>
            </div>

            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.shop_profile.save') }}</button>
                <a href="{{ route('dashboard') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
