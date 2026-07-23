<?php

return [

    'sale_return_description' => 'Sale return :invoice',
    'sale_return_cogs_description' => 'Sale return (cost) :invoice',
    'purchase_return_description' => 'Purchase return',
    'stock_loss_description' => 'Stock loss: :product',

    'errors' => [
        'no_items' => 'A return must have at least one item.',
        'bad_qty' => 'The return quantity must be greater than zero and within the original quantity.',
        'refund_exceeds' => 'The refund cannot exceed the total value.',
    ],
];
