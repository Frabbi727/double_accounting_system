<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('asset.categories.title') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        {{-- Add form --}}
        <form method="POST" action="{{ route('asset-categories.store') }}" class="bg-white rounded-lg shadow p-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            @csrf
            <div>
                <label class="text-sm text-gray-600">{{ __('asset.category_name_bn') }}</label>
                <input name="name_bn" value="{{ old('name_bn') }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('asset.category_name_en') }}</label>
                <input name="name_en" value="{{ old('name_en') }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('asset.categories.account') }}</label>
                <select name="account_id" class="{{ $input }}">
                    <option value="">—</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm w-full">{{ __('ui.common.save') }}</button>
            </div>
        </form>

        {{-- List --}}
        <div class="bg-white rounded-lg shadow divide-y">
            @forelse ($categories as $c)
                <div class="p-4 flex justify-between items-center">
                    <div>
                        <span class="font-medium text-gray-800">{{ $c->name }}</span>
                        <span class="text-xs text-gray-400 ml-2">{{ $c->account?->code }} — {{ $c->account?->name }}</span>
                        @if ($c->is_system)
                            <span class="text-xs rounded-full bg-gray-100 text-gray-500 px-2 py-0.5 ml-2">{{ __('asset.categories.system') }}</span>
                        @endif
                    </div>
                    @unless ($c->is_system)
                        <form method="POST" action="{{ route('asset-categories.destroy', $c) }}"
                              onsubmit="return confirm('{{ __('asset.categories.delete_confirm') }}')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline text-xs">{{ __('ui.product.delete') }}</button>
                        </form>
                    @endunless
                </div>
            @empty
                <div class="p-6 text-center text-gray-400">—</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
