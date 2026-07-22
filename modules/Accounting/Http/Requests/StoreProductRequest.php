<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Accounting\Services\Accounting\PeriodLockService;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO (roles & permissions milestone): replace with
        // $this->user()->can('create', \Modules\Accounting\Models\Product::class);
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:60', 'unique:products,sku'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'cost_price' => ['required', 'numeric', 'gte:0'],
            'sale_price' => ['required', 'numeric', 'gte:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],

            'opening_qty' => ['nullable', 'numeric', 'gte:0'],

            // HARD requirement: stock without a cost makes COGS zero, which
            // would report the entire sale as profit.
            'opening_cost' => ['required_with:opening_qty', 'nullable', 'numeric', 'gt:0'],
            'opening_date' => [
                'nullable',
                'date',
                'before_or_equal:'.config('shop.cutoff_date'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {

            if ((float) $this->input('opening_qty', 0) > 0
                && app(PeriodLockService::class)->isOpeningLocked()) {
                $v->errors()->add('opening_qty', __('accounting.errors.opening_locked_product'));
            }

            // Warning-level check, surfaced to the user but not blocking.
            $cost = (float) $this->input('cost_price', 0);
            $sale = (float) $this->input('sale_price', 0);

            if ($sale > 0 && $cost > 0 && $sale < $cost) {
                session()->flash('warning', __('accounting.warnings.sale_below_cost'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'opening_cost.gt' => __('accounting.errors.opening_cost_gt'),
            'opening_cost.required_with' => __('accounting.errors.opening_cost_required'),
        ];
    }
}
