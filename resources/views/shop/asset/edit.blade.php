<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('asset.edit_title') }} <span class="font-mono text-sm text-gray-400">{{ $asset->asset_no }}</span></h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        {{-- Only non-financial metadata is editable. Amount / account / date are
             immutable — correct a mistake by disposing and re-creating. --}}
        <form method="POST" action="{{ route('assets.update', $asset) }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="text-sm text-gray-600">{{ __('asset.name') }}</label>
                <input name="name" value="{{ old('name', $asset->name) }}" required maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('asset.reference_no') }}</label>
                <input name="reference_no" value="{{ old('reference_no', $asset->reference_no) }}" maxlength="255" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm text-gray-600">{{ __('asset.description_label') }}</label>
                <textarea name="description" maxlength="1000" class="{{ $input }}" rows="2">{{ old('description', $asset->description) }}</textarea>
            </div>

            {{-- Existing documents with remove checkboxes --}}
            @if ($asset->documents->isNotEmpty())
                <div>
                    <label class="text-sm text-gray-600">{{ __('asset.documents') }}</label>
                    <ul class="divide-y text-sm mt-1 border rounded">
                        @foreach ($asset->documents as $doc)
                            <li class="flex items-center justify-between px-3 py-2">
                                <a href="{{ $doc->url() }}" target="_blank" class="text-indigo-600 hover:underline">{{ $doc->original_name }}</a>
                                <label class="text-xs text-red-600 flex items-center gap-1">
                                    <input type="checkbox" name="remove_documents[]" value="{{ $doc->id }}"> {{ __('ui.product.delete') }}
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="text-sm text-gray-600">{{ __('asset.add_document') }}</label>
                <input name="documents[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="text-sm block mt-1">
                <p class="text-xs text-gray-400 mt-1">{{ __('asset.documents_hint') }}</p>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('assets.show', $asset) }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('asset.confirm_back') }}</a>
                <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('asset.update') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
