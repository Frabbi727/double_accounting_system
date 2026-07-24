<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">
                {{ __('asset.details_title') }}
                <span class="font-mono text-sm text-gray-400">{{ $asset->asset_no }}</span>
            </h2>
            @unless ($asset->disposed())
                <a href="{{ route('assets.edit', $asset) }}" class="text-sm text-gray-600 hover:underline">{{ __('asset.edit') }}</a>
            @endunless
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('shop._flash')
        @error('dispose')<div class="rounded bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>@enderror

        @if ($asset->disposed())
            <div class="rounded bg-gray-100 border border-gray-200 text-gray-700 text-sm px-4 py-3">
                {{ __('asset.disposed_banner', ['by' => $asset->disposer?->name ?? '—', 'at' => $asset->disposed_at?->format('d/m/Y H:i')]) }}
                @if ($asset->disposed_reason) — {{ $asset->disposed_reason }} @endif
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Asset information --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.info') }}</h3>
                <dl class="text-sm divide-y">
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.name') }}</dt><dd class="font-medium">{{ $asset->name }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.category') }}</dt><dd>{{ $asset->category?->name }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.current_value') }}</dt><dd class="font-semibold">@taka($asset->amount)</dd></div>
                    @if ($asset->description)
                        <div class="py-1.5"><dt class="text-gray-500">{{ __('asset.description_label') }}</dt><dd class="text-gray-700 mt-1">{{ $asset->description }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Purchase & payment --}}
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.purchase_details') }}</h3>
                <dl class="text-sm divide-y">
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.purchase_date') }}</dt><dd>{{ $asset->purchase_date->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.amount') }}</dt><dd>@taka($asset->amount)</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.vendor') }}</dt><dd>{{ $asset->vendorLabel() ?? '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.reference_no') }}</dt><dd>{{ $asset->reference_no ?? '—' }}</dd></div>
                    <div class="flex justify-between py-1.5">
                        <dt class="text-gray-500">{{ __('asset.payment_mode') }}</dt>
                        <dd>
                            @switch($asset->payment_mode)
                                @case('account') {{ __('asset.mode_account') }} @break
                                @case('credit') {{ __('asset.mode_credit') }} @break
                                @case('opening') {{ __('asset.mode_opening') }} @break
                            @endswitch
                        </dd>
                    </div>
                    @if ($asset->paymentAccount)
                        <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.payment_account') }}</dt><dd>{{ $asset->paymentAccount->code }} — {{ $asset->paymentAccount->name }}</dd></div>
                    @endif
                    @if ($asset->supplier)
                        <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.supplier') }}</dt><dd>{{ $asset->supplier->name }}</dd></div>
                    @endif

                    {{-- On-credit: paid vs remaining due (live, ledger-derived). --}}
                    @if ($asset->payment_mode === 'credit')
                        @if ($asset->disposed())
                            <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.remaining_due') }}</dt><dd class="text-gray-500">—</dd></div>
                        @else
                            <div class="flex justify-between py-1.5">
                                <dt class="text-gray-500">{{ __('asset.remaining_due') }}</dt>
                                <dd class="font-semibold {{ ($supplierDue ?? 0) > 0.005 ? 'text-red-600' : 'text-green-600' }}">@taka($supplierDue ?? 0)</dd>
                            </div>
                            <div class="py-1.5 text-xs text-gray-400">
                                {{ __('asset.due_note') }}
                                @if (Route::has('suppliers.show'))
                                    <a href="{{ route('suppliers.show', $asset->supplier_id) }}" class="text-indigo-600 hover:underline">{{ __('asset.view_supplier') }}</a>
                                @endif
                            </div>
                        @endif
                    @endif
                </dl>
            </div>
        </div>

        {{-- Accounting entry / voucher --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('asset.voucher') }}</h3>
                <span class="text-xs text-gray-400">{{ __('asset.voucher_no') }}: <span class="font-mono">{{ $asset->asset_no }}</span></span>
            </div>

            @if ($asset->journalEntry)
                @php($entry = $asset->journalEntry)
                <p class="text-xs text-gray-500 mb-2">{{ __('asset.entry_date') }}: {{ $entry->date->format('d/m/Y') }} — {{ $entry->description }}</p>
                <table class="min-w-full text-sm mb-4">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">{{ __('asset.account') }}</th>
                            <th class="py-1 text-right">{{ __('asset.debit') }}</th>
                            <th class="py-1 text-right">{{ __('asset.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($entry->lines as $line)
                            <tr>
                                <td class="py-1.5">{{ $line->account->code }} — {{ $line->account->name }}</td>
                                <td class="py-1.5 text-right">@if ($line->debit > 0)@taka($line->debit)@endif</td>
                                <td class="py-1.5 text-right">@if ($line->credit > 0)@taka($line->credit)@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($entry->reversedBy)
                    <p class="text-xs font-semibold text-gray-500 mb-1">{{ __('asset.reversal_chain') }}</p>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y opacity-70">
                            @foreach ($entry->reversedBy->lines as $line)
                                <tr>
                                    <td class="py-1.5">{{ $line->account->code }} — {{ $line->account->name }}</td>
                                    <td class="py-1.5 text-right">@if ($line->debit > 0)@taka($line->debit)@endif</td>
                                    <td class="py-1.5 text-right">@if ($line->credit > 0)@taka($line->credit)@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @else
                <p class="text-sm text-gray-400">—</p>
            @endif
        </div>

        {{-- Documents --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.documents') }}</h3>
            @if ($asset->documents->isNotEmpty())
                <ul class="divide-y text-sm">
                    @foreach ($asset->documents as $doc)
                        <li class="flex items-center justify-between py-2">
                            <span class="text-gray-700">{{ $doc->original_name }}</span>
                            <a href="{{ $doc->url() }}" target="_blank" class="text-indigo-600 hover:underline text-xs">{{ __('asset.download') }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-400">{{ __('asset.no_documents') }}</p>
            @endif
        </div>

        {{-- Audit --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.audit') }}</h3>
            <dl class="text-sm divide-y">
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.created_by') }}</dt><dd>{{ $asset->creator?->name ?? '—' }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.created_at') }}</dt><dd>{{ $asset->created_at?->format('d/m/Y H:i') }}</dd></div>
                @if ($asset->disposed())
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.disposed_by') }}</dt><dd>{{ $asset->disposer?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.disposed_at') }}</dt><dd>{{ $asset->disposed_at?->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('asset.disposed_reason') }}</dt><dd>{{ $asset->disposed_reason ?? '—' }}</dd></div>
                @endif
            </dl>
        </div>

        {{-- Dispose (owner-only) --}}
        @can('entry.delete')
            @unless ($asset->disposed())
                <div class="bg-white rounded-lg shadow p-6 border border-red-100">
                    <h3 class="text-sm font-semibold text-red-700 mb-1">{{ __('asset.dispose') }}</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ __('asset.dispose_hint') }}</p>
                    <form method="POST" action="{{ route('assets.dispose', $asset) }}" class="flex items-end gap-3"
                          onsubmit="return confirm('{{ __('asset.confirm_dispose') }}')">
                        @csrf
                        <div class="flex-1">
                            <label class="text-sm text-gray-600">{{ __('asset.dispose_reason') }}</label>
                            <input name="dispose_reason" required maxlength="255" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm">
                        </div>
                        <button class="bg-red-600 text-white rounded px-4 py-2 text-sm">{{ __('asset.dispose') }}</button>
                    </form>
                </div>
            @endunless
        @endcan
    </div>
</x-app-layout>
