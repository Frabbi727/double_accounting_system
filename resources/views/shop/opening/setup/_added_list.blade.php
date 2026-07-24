{{-- Running list of name + amount rows already entered in this step. --}}
@if ($rows->isEmpty())
    <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">
        {{ __('ui.opening.wizard.' . $emptyKey) }}
    </div>
@else
    <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
            {{ __('ui.opening.wizard.added_count', ['count' => $rows->count()]) }}
        </p>
        <ul class="rounded-lg border border-gray-100 divide-y divide-gray-100 overflow-hidden">
            @foreach ($rows as $row)
                <li class="flex items-center justify-between gap-4 px-4 py-2.5">
                    <span class="text-sm text-gray-700">{{ $row['name'] }}</span>
                    <span class="text-sm font-medium text-gray-900 tabular-nums">@taka($row['amount'])</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
