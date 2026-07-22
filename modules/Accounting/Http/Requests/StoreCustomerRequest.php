<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Accounting\Services\Accounting\PeriodLockService;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO (roles & permissions milestone): replace with
        // $this->user()->can('create', \Modules\Accounting\Models\Customer::class);
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:customers,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'default_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Opening dues — one row per old unpaid invoice, or a single lump sum.
            'opening_items' => ['nullable', 'array'],
            'opening_items.*.amount' => ['required', 'numeric', 'gt:0'],
            'opening_items.*.original_date' => [
                'required',
                'date',
                'before_or_equal:'.config('shop.cutoff_date'),
            ],
            'opening_items.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (empty($this->input('opening_items'))) {
                return;
            }

            // Once the opening period is locked, new opening balances are not
            // allowed — money owed from now on must come from a Sale entry.
            if (app(PeriodLockService::class)->isOpeningLocked()) {
                $v->errors()->add('opening_items', __('accounting.errors.opening_locked_customer'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'opening_items.*.amount.gt' => __('accounting.errors.opening_amount_gt'),
            'opening_items.*.original_date.before_or_equal' => __('accounting.errors.opening_date_before_cutoff', [
                'cutoff' => config('shop.cutoff_date'),
            ]),
        ];
    }
}
