@php
    $input = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm';
    $isEdit = isset($product);

    // Resolve the currently-selected category/sub-category so both selects
    // pre-fill on edit (and after a validation error).
    $selectedLeaf = old('product_category_id', $isEdit ? $product->product_category_id : null);
    $selectedCategory = '';
    $selectedSub = '';
    if ($selectedLeaf) {
        $node = \Modules\Accounting\Models\ProductCategory::find($selectedLeaf);
        if ($node) {
            if ($node->parent_id) {
                $selectedSub = (string) $node->id;
                $selectedCategory = (string) $node->parent_id;
            } else {
                $selectedCategory = (string) $node->id;
            }
        }
    }

    // Top-level categories as an ARRAY (Alpine x-for cannot iterate objects) and
    // a parent-id => [subs] map. Passed via @js so quotes are HTML-attribute-safe.
    $parents = $categories->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name])->values()->all();
    $subMap = [];
    foreach ($categories as $c) {
        $subMap[(string) $c->id] = $c->children
            ->map(fn ($ch) => ['id' => (string) $ch->id, 'name' => $ch->name])->values()->all();
    }

    // Unit: choose from the seeded list, or "other" to free-type. The stored
    // value is the unit string either way.
    $unitNames = $units->pluck('name')->all();
    $currentUnit = old('unit', $isEdit ? $product->unit : ($unitNames[0] ?? 'পিস'));
    $unitIsOther = ! in_array($currentUnit, $unitNames, true);
    $unitChoiceInit = $unitIsOther ? '__other__' : $currentUnit;
    $unitCustomInit = $unitIsOther ? $currentUnit : '';
@endphp

<form method="POST" action="{{ $action }}"
      class="bg-white rounded-lg shadow p-6 space-y-4"
      x-data="productForm({
          parents: @js($parents),
          subMap: @js($subMap),
          category: @js($selectedCategory),
          sub: @js($selectedSub),
          unitChoice: @js($unitChoiceInit),
          unitCustom: @js($unitCustomInit),
      })">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div>
        <label class="text-sm text-gray-600">{{ __('ui.product.name') }}</label>
        <input name="name" value="{{ old('name', $isEdit ? $product->name : '') }}" required class="{{ $input }}">
    </div>

    <div class="grid grid-cols-2 gap-4">
        {{-- Category (top-level) --}}
        <div>
            <label class="text-sm text-gray-600">{{ __('ui.product.category') }}</label>
            <select x-model="category" @change="sub=''" class="{{ $input }}">
                <option value="">— {{ __('ui.product.none') }} —</option>
                <template x-for="c in parents" :key="c.id">
                    <option :value="c.id" x-text="c.name"></option>
                </template>
            </select>
        </div>
        {{-- Sub-category (depends on the chosen category) --}}
        <div>
            <label class="text-sm text-gray-600">{{ __('ui.product.sub_category') }}</label>
            <select x-model="sub" :disabled="!category || subs.length === 0" class="{{ $input }} disabled:bg-gray-100">
                <option value="">— {{ __('ui.product.none') }} —</option>
                <template x-for="s in subs" :key="s.id">
                    <option :value="s.id" x-text="s.name"></option>
                </template>
            </select>
        </div>
    </div>
    {{-- Leaf actually stored: sub-category if chosen, else the category. --}}
    <input type="hidden" name="product_category_id" :value="sub || category">

    <button type="button" @click="showAdd = true"
            class="text-xs text-blue-600 hover:underline">+ {{ __('ui.product.add_category') }}</button>

    <div class="grid grid-cols-2 gap-4">
        {{-- Unit: visible dropdown of seeded units + an "other" free-type option --}}
        <div>
            <label class="text-sm text-gray-600">{{ __('ui.product.unit') }}</label>
            <select x-model="unitChoice" class="{{ $input }}">
                @foreach ($units as $u)
                    <option value="{{ $u->name }}">{{ $u->name }}</option>
                @endforeach
                <option value="__other__">{{ __('ui.product.unit_other') }}</option>
            </select>
            <input x-show="unitChoice === '__other__'" x-model="unitCustom"
                   placeholder="{{ __('ui.product.unit') }}" class="{{ $input }}">
            <input type="hidden" name="unit" :value="unitChoice === '__other__' ? unitCustom : unitChoice">
        </div>
        @if ($isEdit && $product->sku)
            {{-- SKU is system-generated and immutable — shown read-only. --}}
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.product.sku') }}</label>
                <input value="{{ $product->sku }}" disabled class="{{ $input }} bg-gray-100 text-gray-500">
            </div>
        @endif
        <div>
            <label class="text-sm text-gray-600">{{ __('ui.product.reorder') }}</label>
            <input name="reorder_level" type="number" min="0" value="{{ old('reorder_level', $isEdit ? $product->reorder_level : 0) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="text-sm text-gray-600">{{ __('ui.product.sale_price') }}</label>
            <input name="sale_price" type="number" step="0.01" min="0" value="{{ old('sale_price', $isEdit ? $product->sale_price : 0) }}" required class="{{ $input }}">
        </div>
    </div>

    @if (! $isEdit)
        {{-- Opening cost + opening stock — only meaningful when first creating.
             Editing goes through correctOpeningStock, not this form. --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.product.cost_price') }}</label>
                <input name="cost_price" type="number" step="0.0001" min="0" value="{{ old('cost_price', 0) }}" required class="{{ $input }}">
            </div>
        </div>

        @if ($openingLocked ?? false)
            @include('shop._opening_locked_note')
        @else
            <fieldset class="border-t pt-4">
                <legend class="text-sm font-medium text-gray-700">{{ __('ui.product.opening_qty') }}</legend>
                <div class="grid grid-cols-2 gap-4 mt-2">
                    <div>
                        <label class="text-sm text-gray-600">{{ __('ui.product.opening_qty') }}</label>
                        <input name="opening_qty" type="number" step="0.001" min="0" value="{{ old('opening_qty') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">{{ __('ui.product.opening_cost') }}</label>
                        <input name="opening_cost" type="number" step="0.0001" min="0" value="{{ old('opening_cost') }}" class="{{ $input }}">
                    </div>
                </div>
            </fieldset>
        @endif
    @endif

    <div class="flex gap-3">
        <button class="bg-gray-800 text-white rounded px-4 py-2 text-sm">{{ __('ui.common.save') }}</button>
        <a href="{{ route('products.index') }}" class="text-gray-500 px-4 py-2 text-sm">{{ __('ui.common.cancel') }}</a>
    </div>

    {{-- Inline add category / sub-category overlay (AJAX, keeps form state) --}}
    <div x-show="showAdd" x-cloak class="fixed inset-0 bg-gray-900/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md space-y-4" @click.outside="showAdd = false">
            <h3 class="font-semibold text-gray-800">{{ __('ui.product.add_category') }}</h3>
            <p x-show="addError" x-text="addError" class="text-sm text-red-600"></p>
            <div>
                <label class="text-sm text-gray-600">{{ __('ui.category.parent') }}</label>
                <select x-model="addParent" class="{{ $input }}">
                    <option value="">— {{ __('ui.category.top_level') }} —</option>
                    <template x-for="c in parents" :key="c.id">
                        <option :value="c.id" x-text="c.name"></option>
                    </template>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.category.name_bn') }}</label>
                    <input x-model="addBn" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm text-gray-600">{{ __('ui.category.name_en') }}</label>
                    <input x-model="addEn" class="{{ $input }}">
                </div>
            </div>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showAdd = false" class="text-gray-500 px-3 py-1.5 text-sm">{{ __('ui.common.cancel') }}</button>
                <button type="button" @click="saveCategory()" :disabled="saving" class="bg-green-600 text-white rounded px-4 py-1.5 text-sm disabled:opacity-50">{{ __('ui.common.save') }}</button>
            </div>
        </div>
    </div>
</form>

<script>
    function productForm(init) {
        return {
            parents: init.parents,
            subMap: init.subMap,
            category: init.category,
            sub: init.sub,
            unitChoice: init.unitChoice,
            unitCustom: init.unitCustom,
            showAdd: false,
            addParent: '',
            addBn: '',
            addEn: '',
            addError: '',
            saving: false,
            get subs() {
                return this.subMap[this.category] || [];
            },
            async saveCategory() {
                this.addError = '';
                if (!this.addBn || !this.addEn) {
                    this.addError = @js(__('ui.category.name_required'));
                    return;
                }
                this.saving = true;
                try {
                    const res = await fetch(@js(route('product-categories.store')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            name_bn: this.addBn,
                            name_en: this.addEn,
                            parent_id: this.addParent || null,
                            inline: true,
                        }),
                    });
                    if (!res.ok) {
                        this.addError = @js(__('ui.category.save_failed'));
                        this.saving = false;
                        return;
                    }
                    const row = await res.json();
                    if (row.parent_id) {
                        // New sub-category: add under its parent, then select both.
                        const pid = String(row.parent_id);
                        if (!this.subMap[pid]) this.subMap[pid] = [];
                        this.subMap[pid].push({ id: String(row.id), name: row.name });
                        this.category = pid;
                        this.sub = String(row.id);
                    } else {
                        // New top-level category.
                        this.parents.push({ id: String(row.id), name: row.name });
                        this.category = String(row.id);
                        this.sub = '';
                    }
                    this.addBn = this.addEn = this.addParent = '';
                    this.showAdd = false;
                } catch (e) {
                    this.addError = @js(__('ui.category.save_failed'));
                }
                this.saving = false;
            },
        };
    }
</script>
