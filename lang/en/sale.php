<?php

return [

    'description' => 'Sale :invoice',
    'cogs_description' => 'Cost of goods sold :invoice',

    'errors' => [
        'no_items' => 'A sale must have at least one item.',
        'line_invalid' => 'Each sale line must have a quantity greater than zero and a non-negative price.',
        'line_discount_invalid' => 'A line discount must be non-negative and within the line total.',
        'zero_revenue' => 'The sale total must be greater than zero.',
        'discount_exceeds' => 'The discount cannot exceed the gross total.',
        'paid_exceeds' => 'The paid amount cannot exceed the net amount due.',
        'credit_needs_customer' => 'A credit sale (with an unpaid balance) must name a customer, otherwise the due belongs to nobody.',
    ],
];
