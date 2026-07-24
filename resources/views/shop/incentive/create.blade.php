<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('ui.incentive.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')
        <form method="POST" action="{{ route('incentives.store') }}" class="bg-white rounded-lg shadow p-6 space-y-4 relative"
              x-data="incentiveForm({
                  customers: {{ Illuminate\Support\Js::from($customers->pluck('name', 'id')) }},
                  suppliers: {{ Illuminate\Support\Js::from($suppliers->pluck('name', 'id')) }},
                  customerDues: {{ Illuminate\Support\Js::from($customerDues) }},
                  supplierDues: {{ Illuminate\Support\Js::from($supplierDues) }},
                  customerDocs: {{ Illuminate\Support\Js::from($customerDocs) }},
                  supplierDocs: {{ Illuminate\Support\Js::from($supplierDocs) }},
                  accountBalances: {{ Illuminate\Support\Js::from($accountBalances) }},
                  firstAccount: '{{ $accounts->first()->id ?? '' }}',
                  labels: {{ Illuminate\Support\Js::from([
                      'fixed' => __('ui.incentive.basis_fixed'),
                      'pct_of_due' => __('ui.incentive.basis_pct_due'),
                      'pct_of_invoice' => __('ui.incentive.basis_pct_invoice'),
                      'pct_of_sales' => __('ui.incentive.basis_pct_sales'),
                      'received' => __('ui.incentive.received'),
                      'given' => __('ui.incentive.given'),
                      'settle_cash' => __('ui.incentive.settle_cash'),
                      'settle_due' => __('ui.incentive.settle_due'),
                  ]) }},
              })">
            @csrf

            {{-- Direction --}}
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.incentive.direction') }}</label>
                <select name="direction" x-model="direction" @change="partyId=''; refDocId=''" class="{{ $input }}">
                    <option value="received">{{ __('ui.incentive.received') }}</option>
                    <option value="given">{{ __('ui.incentive.given') }}</option>
                </select>
            </div>

            {{-- Party (two static selects; inactive one disabled so it never submits) --}}
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.incentive.party') }}</label>
                <select name="party_id" x-model="partyId" x-show="direction==='received'" :disabled="direction!=='received'" class="{{ $input }}">
                    <option value="">— {{ __('ui.incentive.select_party') }} —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <select name="party_id" x-model="partyId" x-show="direction==='given'" :disabled="direction!=='given'" class="{{ $input }}">
                    <option value="">— {{ __('ui.incentive.select_party') }} —</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs" x-show="partyId && due!==null">
                    <span class="text-gray-500">{{ __('ui.incentive.current_due') }}:</span>
                    <span class="font-semibold" x-text="fmt(due)"></span>
                </p>
            </div>

            {{-- Basis --}}
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.incentive.basis') }}</label>
                <select name="basis" x-model="basis" class="{{ $input }}">
                    <option value="fixed">{{ __('ui.incentive.basis_fixed') }}</option>
                    <option value="pct_of_due">{{ __('ui.incentive.basis_pct_due') }}</option>
                    <option value="pct_of_invoice">{{ __('ui.incentive.basis_pct_invoice') }}</option>
                    <option value="pct_of_sales">{{ __('ui.incentive.basis_pct_sales') }}</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                {{-- Fixed amount --}}
                <div x-show="basis==='fixed'">
                    <label class="text-sm text-gray-600">{{ __('ui.incentive.amount') }}</label>
                    <input name="amount" type="number" step="0.01" min="0" x-model="amountInput" :disabled="basis!=='fixed'" class="{{ $input }}">
                </div>
                {{-- Percentage rate --}}
                <div x-show="basis!=='fixed'">
                    <label class="text-sm text-gray-600">{{ __('ui.incentive.rate') }}</label>
                    <input name="rate" type="number" step="0.01" min="0" x-model="rate" :disabled="basis==='fixed'" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.incentive.date') }}</label>
                    <input name="date" type="date" x-model="date" required class="{{ $input }}">
                </div>
            </div>

            {{-- Invoice picker (pct_of_invoice) --}}
            <div x-show="basis==='pct_of_invoice'">
                <label class="text-sm text-gray-600">{{ __('ui.incentive.invoice') }}</label>
                <select name="ref_doc_id" x-model="refDocId" :disabled="basis!=='pct_of_invoice'" class="{{ $input }}">
                    <option value="">— {{ __('ui.incentive.select_invoice') }} —</option>
                    <template x-for="d in docs" :key="d.id">
                        <option :value="d.id" x-text="d.label + ' · ' + fmt(d.total)"></option>
                    </template>
                </select>
            </div>

            {{-- Period (pct_of_sales / sell %) --}}
            <div class="grid grid-cols-2 gap-4" x-show="basis==='pct_of_sales'">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.incentive.period_from') }}</label>
                    <input name="period_from" type="date" x-model="periodFrom" :disabled="basis!=='pct_of_sales'" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.incentive.period_to') }}</label>
                    <input name="period_to" type="date" x-model="periodTo" :disabled="basis!=='pct_of_sales'" class="{{ $input }}">
                </div>
            </div>

            {{-- Live preview of the computed amount --}}
            <div class="rounded bg-gray-50 px-4 py-3 text-sm" x-show="basis!=='fixed'">
                <span class="text-gray-500">{{ __('ui.incentive.base') }}:</span>
                <span class="font-medium" x-text="fmt(base)"></span>
                <span class="text-gray-400 mx-2">×</span>
                <span x-text="(Number(rate)||0) + '%'"></span>
                <span class="text-gray-400 mx-2">=</span>
                <span class="font-semibold" x-text="amount===null ? '{{ __('ui.incentive.computed') }} (server)' : fmt(amount)"></span>
            </div>

            {{-- Settle mode --}}
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.incentive.settle_mode') }}</label>
                <select name="settle_mode" x-model="settleMode" class="{{ $input }}">
                    <option value="cash">{{ __('ui.incentive.settle_cash') }}</option>
                    <option value="due">{{ __('ui.incentive.settle_due') }}</option>
                </select>
                <p class="mt-1 text-xs text-amber-600" x-show="needsParty">{{ __('incentive.errors.due_needs_party') }}</p>
            </div>

            {{-- Cash/bank account (settle cash) --}}
            <div x-show="settleMode==='cash'">
                <label class="text-sm text-gray-600">{{ __('ui.incentive.account') }}</label>
                <select name="account_id" x-model="accountId" :disabled="settleMode!=='cash'" class="{{ $input }}">
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500" x-show="direction==='given' && sourceBalance!==null">
                    {{ __('ui.finance.available') }}: <span class="font-semibold" x-text="fmt(sourceBalance)"></span>
                </p>
                <p class="mt-1 text-xs text-red-600" x-show="insufficient">{{ __('ui.finance.insufficient') }}</p>
            </div>
            <div x-show="settleMode==='due'">
                <p class="text-xs text-gray-500" x-show="partyId" x-text="'{{ __('ui.incentive.reduces_due') }}'"></p>
                <p class="mt-1 text-xs text-red-600" x-show="overDue">{{ __('ui.incentive.exceeds_due') }}</p>
            </div>

            <div>
                <label class="text-sm text-gray-600">{{ __('ui.incentive.note') }}</label>
                <input name="notes" x-model="notes" class="{{ $input }}">
            </div>

            @error('amount')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex gap-3">
                <button type="button" @click="review()" :disabled="invalid"
                        class="bg-gray-800 text-white rounded px-4 py-2 text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ __('ui.incentive.review') }}
                </button>
                <a href="{{ route('incentives.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
            </div>

            {{-- Confirmation dialog (shares the form's Alpine state) --}}
            @include('shop.incentive._confirm', ['confirmKind' => __('ui.incentive.kind_incentive')])
        </form>
    </div>

    <script>
        function incentiveForm(cfg) {
            return {
                ...cfg,
                direction: 'received',
                settleMode: 'cash',
                partyId: '',
                basis: 'fixed',
                amountInput: '',
                rate: '',
                refDocId: '',
                periodFrom: '{{ now()->startOfMonth()->toDateString() }}',
                periodTo: '{{ now()->toDateString() }}',
                accountId: cfg.firstAccount,
                notes: '',
                date: '{{ old('date', now()->toDateString()) }}',
                confirming: false,

                get partyType() { return this.direction === 'received' ? 'supplier' : 'customer'; },
                get partyName() {
                    const map = this.direction === 'received' ? this.suppliers : this.customers;
                    return map[this.partyId] || '';
                },
                get dueMap() { return this.direction === 'received' ? this.supplierDues : this.customerDues; },
                get due() { const d = this.dueMap[this.partyId]; return d === undefined || d === null ? null : Number(d); },
                get docs() { return (this.direction === 'received' ? this.supplierDocs : this.customerDocs)[this.partyId] || []; },
                get selectedDoc() { return this.docs.find(d => String(d.id) === String(this.refDocId)) || null; },
                get base() {
                    if (this.basis === 'fixed') return null;
                    if (this.basis === 'pct_of_due') return this.due;
                    if (this.basis === 'pct_of_invoice') return this.selectedDoc ? this.selectedDoc.total : null;
                    return null; // pct_of_sales — turnover known only server-side
                },
                get amount() {
                    if (this.basis === 'fixed') return Number(this.amountInput) || 0;
                    if (this.base === null || this.base === undefined) return null;
                    return Math.round((Number(this.rate) / 100 * this.base) * 100) / 100;
                },
                get sourceBalance() { const b = this.accountBalances[this.accountId]; return b === undefined || b === null ? null : Number(b); },
                get overDue() { return this.settleMode === 'due' && this.due !== null && this.amount !== null && this.amount > this.due + 0.005; },
                get insufficient() { return this.settleMode === 'cash' && this.direction === 'given' && this.sourceBalance !== null && this.amount !== null && this.amount > this.sourceBalance + 0.005; },
                get needsParty() { return this.settleMode === 'due' && !this.partyId; },
                get invalid() {
                    if (this.needsParty) return true;
                    if (this.basis === 'fixed' && !(Number(this.amountInput) > 0)) return true;
                    if (this.basis !== 'fixed' && !(Number(this.rate) > 0)) return true;
                    if (this.basis === 'pct_of_invoice' && !this.refDocId) return true;
                    if (this.basis === 'pct_of_sales' && (!this.periodFrom || !this.periodTo)) return true;
                    if (this.amount !== null && !(this.amount > 0)) return true;
                    return this.overDue || this.insufficient;
                },
                get basisLabel() { return this.labels[this.basis]; },
                get settleLabel() { return this.settleMode === 'cash' ? this.labels.settle_cash : this.labels.settle_due; },
                get directionLabel() { return this.labels[this.direction]; },
                fmt(n) { return n === null || n === undefined ? '—' : '৳ ' + Number(n).toLocaleString('bn-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                review() { if (!this.invalid) this.confirming = true; },
            };
        }
    </script>
</x-app-layout>
