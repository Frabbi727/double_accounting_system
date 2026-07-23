<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.user.title') }}</h2>
            <a href="{{ route('users.create') }}" class="bg-gray-800 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.user.new') }}</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @error('user')<div class="mb-4 text-sm text-red-600">{{ $message }}</div>@enderror

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ __('ui.user.name') }}</th>
                        <th class="px-4 py-2">{{ __('ui.user.email') }}</th>
                        <th class="px-4 py-2">{{ __('ui.user.role') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('ui.user.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($users as $u)
                        @php($role = $u->roles->first()?->name)
                        <tr>
                            <td class="px-4 py-2">{{ $u->name }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $u->email }}</td>
                            <td class="px-4 py-2">{{ $role ? __('ui.user.roles.'.$role) : '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('users.edit', $u) }}" class="text-blue-600 hover:underline">{{ __('ui.user.edit') }}</a>
                                @if ($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline ml-2"
                                          onsubmit="return confirm('{{ __('ui.user.delete_confirm') }}')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">{{ __('ui.user.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
