<?php

return [

    'received_description' => 'Incentive received',
    'paid_description' => 'Incentive paid',
    'rebate_description' => 'Rebate: :product',

    'errors' => [
        'amount_positive' => 'The amount must be greater than zero.',
        'rate_positive' => 'The percentage must be greater than zero.',
        'base_zero' => 'The calculation base is zero — a percentage cannot be applied to it.',
        'unknown_basis' => 'Unknown calculation basis.',
        'due_needs_party' => 'Select a party (customer/supplier) to settle against a due.',
        'exceeds_due' => 'The settled amount cannot exceed the current due (:due).',
        'rebate_no_stock' => 'No stock on hand for :product; the rebate cannot be applied.',
        'rebate_exceeds_value' => 'The rebate cannot exceed the current stock value.',
    ],
];
