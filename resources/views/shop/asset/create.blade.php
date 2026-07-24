<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">{{ __('asset.new') }}</h2>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('shop._flash')
        @php($input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm')

        @error('amount')<div class="mb-4 rounded bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>@enderror

        <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data"
              class="bg-white rounded-lg shadow p-6 space-y-5"
              x-data="assetForm({
                  categories: {{ Illuminate\Support\Js::from($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'account' => optional($c->account)->code.' — '.optional($c->account)->name])) }},
                  accounts: {{ Illuminate\Support\Js::from($paymentAccounts->map(fn($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name])) }},
                  suppliers: {{ Illuminate\Support\Js::from($suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])) }},
                  defaultAccountId: {{ Illuminate\Support\Js::from($defaultAccountId) }},
                  today: {{ Illuminate\Support\Js::from(now()->toDateString()) }},
                  storeUrl: {{ Illuminate\Support\Js::from(route('asset-categories.store')) }},
                  labels: {{ Illuminate\Support\Js::from(['payable' => __('asset.accounts_payable'), 'equity' => __('asset.owner_equity')]) }}
              })"
              @submit="confirming = false">
            @csrf

            {{-- Asset information --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.info') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.category') }}</label>
                        <div class="flex gap-2 items-end">
                            <select name="asset_category_id" x-model.number="categoryId" required class="{{ $input }}">
                                <option value="">—</option>
                                <template x-for="c in categories" :key="c.id">
                                    <option :value="c.id" x-text="c.name"></option>
                                </template>
                            </select>
                            <button type="button" @click="addingCategory = true" class="text-xs text-indigo-600 whitespace-nowrap pb-2">{{ __('asset.add_category') }}</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.name') }}</label>
                        <input name="name" x-model="name" required maxlength="255" class="{{ $input }}">
                    </div>
                </div>
            </div>

            {{-- Purchase details --}}
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.purchase_details') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.purchase_date') }}</label>
                        <input name="purchase_date" type="date" x-model="date" required class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.amount') }}</label>
                        <input name="amount" type="number" step="0.01" min="0.01" x-model.number="amount" required class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.reference_no') }}</label>
                        <input name="reference_no" x-model="referenceNo" maxlength="255" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.description_label') }}</label>
                        <input name="description" x-model="description" maxlength="1000" class="{{ $input }}">
                    </div>
                </div>
            </div>

            {{-- Payment information --}}
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('asset.payment_info') }}</h3>

                <div class="space-y-2">
                    <label class="text-sm text-gray-600">{{ __('asset.payment_mode') }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <label class="flex items-start gap-2 rounded border p-3 cursor-pointer" :class="mode === 'account' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" name="payment_mode" value="account" x-model="mode" class="mt-1">
                            <span><span class="block text-sm font-medium">{{ __('asset.mode_account') }}</span><span class="block text-xs text-gray-500">{{ __('asset.mode_account_hint') }}</span></span>
                        </label>
                        <label class="flex items-start gap-2 rounded border p-3 cursor-pointer" :class="mode === 'credit' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" name="payment_mode" value="credit" x-model="mode" class="mt-1">
                            <span><span class="block text-sm font-medium">{{ __('asset.mode_credit') }}</span><span class="block text-xs text-gray-500">{{ __('asset.mode_credit_hint') }}</span></span>
                        </label>
                        <label class="flex items-start gap-2 rounded border p-3 cursor-pointer" :class="mode === 'opening' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'">
                            <input type="radio" name="payment_mode" value="opening" x-model="mode" class="mt-1">
                            <span><span class="block text-sm font-medium">{{ __('asset.mode_opening') }}</span><span class="block text-xs text-gray-500">{{ __('asset.mode_opening_hint') }}</span></span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    {{-- account mode: which account paid --}}
                    <div x-show="mode === 'account'">
                        <label class="text-sm text-gray-600">{{ __('asset.payment_account') }}</label>
                        <select name="payment_account_id" x-model.number="paymentAccountId" :disabled="mode !== 'account'" class="{{ $input }}">
                            @foreach ($paymentAccounts as $a)
                                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- credit mode: which supplier is owed --}}
                    <div x-show="mode === 'credit'">
                        <label class="text-sm text-gray-600">{{ __('asset.supplier') }}</label>
                        <select name="supplier_id" x-model.number="supplierId" :disabled="mode !== 'credit'" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- free-text vendor (for account / opening) --}}
                    <div x-show="mode !== 'credit'">
                        <label class="text-sm text-gray-600">{{ __('asset.vendor_name') }}</label>
                        <input name="vendor_name" x-model="vendorName" maxlength="255" class="{{ $input }}" :disabled="mode === 'credit'">
                        <p class="text-xs text-gray-400 mt-1">{{ __('asset.vendor_hint') }}</p>
                    </div>
                </div>
            </div>

            {{-- Documents --}}
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">{{ __('asset.documents') }}</h3>
                <p class="text-xs text-gray-400 mb-2">{{ __('asset.documents_hint') }}</p>
                <input name="documents[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"
                       @change="docCount = $event.target.files.length" class="text-sm">
            </div>

            <div class="border-t pt-4">
                <button type="button" @click="confirming = true" class="bg-gray-800 text-white rounded px-4 py-2 text-sm"
                        :disabled="!canSubmit" :class="!canSubmit ? 'opacity-50 cursor-not-allowed' : ''">
                    {{ __('asset.save') }}
                </button>
            </div>

            @include('shop.asset._confirm')

            {{-- Inline add-category overlay --}}
            <div x-show="addingCategory" x-cloak style="display:none" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-gray-900/50" @click="addingCategory = false"></div>
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-sm p-6 space-y-3" @keydown.escape.window="addingCategory = false">
                    <h3 class="font-semibold text-gray-800">{{ __('asset.add_category') }}</h3>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.category_name_bn') }}</label>
                        <input x-model="newCatBn" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('asset.category_name_en') }}</label>
                        <input x-model="newCatEn" class="{{ $input }}">
                    </div>
                    <div class="flex gap-3 justify-end pt-1">
                        <button type="button" @click="addingCategory = false" class="text-gray-500 px-3 py-1.5 text-sm">{{ __('asset.confirm_back') }}</button>
                        <button type="button" @click="saveCategory()" class="bg-indigo-600 text-white rounded px-3 py-1.5 text-sm">{{ __('ui.common.save') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function assetForm(config) {
            return {
                categories: config.categories,
                accounts: config.accounts,
                suppliers: config.suppliers,
                labels: config.labels,
                storeUrl: config.storeUrl,

                categoryId: config.categories.length ? config.categories[0].id : '',
                name: '',
                date: config.today,
                amount: null,
                referenceNo: '',
                description: '',
                mode: 'account',
                paymentAccountId: config.defaultAccountId,
                supplierId: '',
                vendorName: '',
                docCount: 0,

                confirming: false,
                addingCategory: false,
                newCatBn: '',
                newCatEn: '',

                taka(n) { return '৳' + (Math.round((Number(n) || 0) * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

                get canSubmit() {
                    if (!this.categoryId || !this.name || !(Number(this.amount) > 0)) return false;
                    if (this.mode === 'account' && !this.paymentAccountId) return false;
                    if (this.mode === 'credit' && !this.supplierId) return false;
                    return true;
                },
                get selectedCategory() {
                    return this.categories.find(c => c.id === Number(this.categoryId));
                },
                get debitLabel() {
                    return this.selectedCategory ? this.selectedCategory.account : '';
                },
                get creditLabel() {
                    if (this.mode === 'account') {
                        const a = this.accounts.find(x => x.id === Number(this.paymentAccountId));
                        return a ? a.label : '';
                    }
                    if (this.mode === 'credit') {
                        const s = this.suppliers.find(x => x.id === Number(this.supplierId));
                        return this.labels.payable + (s ? ' — ' + s.name : '');
                    }
                    return this.labels.equity;
                },
                get vendorLabel() {
                    if (this.mode === 'credit') {
                        const s = this.suppliers.find(x => x.id === Number(this.supplierId));
                        return s ? s.name : '';
                    }
                    return this.vendorName;
                },

                async saveCategory() {
                    if (!this.newCatBn || !this.newCatEn) return;
                    const res = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content
                                ?? document.querySelector('input[name=_token]').value,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ name_bn: this.newCatBn, name_en: this.newCatEn, inline: true }),
                    });
                    if (!res.ok) return;
                    const cat = await res.json();
                    this.categories.push({ id: cat.id, name: cat.name, account: '' });
                    this.categoryId = cat.id;
                    this.newCatBn = this.newCatEn = '';
                    this.addingCategory = false;
                },
            };
        }
    </script>
</x-app-layout>
