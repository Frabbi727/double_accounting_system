<?php

return [

    'description' => 'Purchase :invoice',

    'errors' => [
        'no_items' => 'A purchase must have at least one item.',
        'line_positive' => 'Each purchase line must have a quantity and cost greater than zero.',
        'paid_exceeds_total' => 'The paid amount cannot exceed the total.',
    ],
];
