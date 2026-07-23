<?php

return [

    'received_description' => 'Incentive received',
    'paid_description' => 'Incentive paid',
    'rebate_description' => 'Rebate: :product',

    'errors' => [
        'amount_positive' => 'The amount must be greater than zero.',
        'rebate_no_stock' => 'No stock on hand for :product; the rebate cannot be applied.',
        'rebate_exceeds_value' => 'The rebate cannot exceed the current stock value.',
    ],
];
