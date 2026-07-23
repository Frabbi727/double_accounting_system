@if (session('status'))
    <div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2 text-sm">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="mb-4 rounded bg-yellow-100 text-yellow-800 px-4 py-2 text-sm">{{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded bg-red-100 text-red-800 px-4 py-2 text-sm">
        <ul class="list-disc ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
