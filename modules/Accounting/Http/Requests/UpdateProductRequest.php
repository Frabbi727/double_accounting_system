<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('master.manage');
    }

    public function rules(): array
    {
        // The bound route model — used to ignore this product's own SKU.
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($productId)],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'sale_price' => ['required', 'numeric', 'gte:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            // cost_price is intentionally absent — it is a weighted average
            // maintained by CostingService, never edited on this form.
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $cost = (float) $this->route('product')?->cost_price;
            $sale = (float) $this->input('sale_price', 0);

            if ($sale > 0 && $cost > 0 && $sale < $cost) {
                session()->flash('warning', __('accounting.warnings.sale_below_cost'));
            }
        });
    }
}
