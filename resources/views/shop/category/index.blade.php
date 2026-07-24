<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.category.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        {{-- Add form --}}
        <form method="POST" action="{{ route('product-categories.store') }}" class="bg-white rounded-lg shadow p-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.category.name_bn') }}</label>
                <input name="name_bn" value="{{ old('name_bn') }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.category.name_en') }}</label>
                <input name="name_en" value="{{ old('name_en') }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.category.parent') }}</label>
                <select name="parent_id" class="{{ $input }}">
                    <option value="">— {{ __('ui.category.top_level') }} —</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('parent_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm w-full">{{ __('ui.common.save') }}</button>
            </div>
        </form>

        {{-- Tree --}}
        <div class="bg-white rounded-lg shadow divide-y">
            @forelse ($categories as $c)
                <div class="p-4">
                    <div class="flex justify-between items-center">
                        <span class="font-medium text-gray-800">{{ $c->name }}</span>
                        <form method="POST" action="{{ route('product-categories.destroy', $c) }}"
                              onsubmit="return confirm('{{ __('ui.category.delete_confirm') }}')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline text-xs">{{ __('ui.product.delete') }}</button>
                        </form>
                    </div>
                    @if ($c->children->isNotEmpty())
                        <ul class="mt-2 ml-4 space-y-1">
                            @foreach ($c->children as $sub)
                                <li class="flex justify-between items-center text-sm text-gray-600">
                                    <span>↳ {{ $sub->name }}</span>
                                    <form method="POST" action="{{ route('product-categories.destroy', $sub) }}"
                                          onsubmit="return confirm('{{ __('ui.category.delete_confirm') }}')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline text-xs">{{ __('ui.product.delete') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-gray-400">{{ __('ui.common.no_data') }}</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
