{{-- One journal entry: its description + debit/credit lines. --}}
<div class="mb-4 last:mb-0">
    <div class="text-xs text-gray-500 mb-1">
        {{ __('return.voucher_no') }} #{{ $entry->id }} · {{ $entry->date->format('d/m/Y') }} · {{ $entry->description }}
    </div>
    <table class="min-w-full text-sm border-t">
        <thead class="text-gray-400 text-left text-xs">
            <tr>
                <th class="py-1">{{ __('return.account') }}</th>
                <th class="py-1 text-right">{{ __('return.debit') }}</th>
                <th class="py-1 text-right">{{ __('return.credit') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach ($entry->lines as $line)
                <tr>
                    <td class="py-1">{{ $line->account?->code }} — {{ $line->account?->name }}</td>
                    <td class="py-1 text-right">{{ (float) $line->debit > 0 ? \App\Support\Money::taka($line->debit) : '' }}</td>
                    <td class="py-1 text-right">{{ (float) $line->credit > 0 ? \App\Support\Money::taka($line->credit) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
