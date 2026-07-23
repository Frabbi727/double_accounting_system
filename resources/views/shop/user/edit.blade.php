<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.user.title') }} — {{ $user->name }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        @php($currentRole = $user->roles->first()?->name)
        <form method="POST" action="{{ route('users.update', $user) }}" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf @method('PATCH')
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.user.name') }}</label>
                <input name="name" value="{{ old('name', $user->name) }}" required class="{{ $input }}">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.user.email') }}</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" required class="{{ $input }}">
                @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.user.password') }}</label>
                    <input name="password" type="password" class="{{ $input }}">
                    <p class="text-xs text-gray-400 mt-1">{{ __('ui.user.password_hint') }}</p>
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.user.role') }}</label>
                    <select name="role" required class="{{ $input }}">
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>{{ __('ui.user.roles.'.$role) }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex gap-3">
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.user.save') }}</button>
                <a href="{{ route('users.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
