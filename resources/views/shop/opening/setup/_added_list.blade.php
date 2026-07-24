{{-- Running list of name + amount rows already entered in this step. --}}
<div class="border-t pt-4">
    @if ($rows->isEmpty())
        <p class="text-sm text-gray-400">{{ __('ui.opening.wizard.' . $emptyKey) }}</p>
    @else
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
            {{ __('ui.opening.wizard.added_count', ['count' => $rows->count()]) }}
        </p>
        <dl class="text-sm divide-y">
            @foreach ($rows as $row)
                <div class="flex justify-between py-1.5">
                    <dt class="text-gray-700">{{ $row['name'] }}</dt>
                    <dd class="font-medium">@taka($row['amount'])</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
