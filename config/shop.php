<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cut-off date
    |--------------------------------------------------------------------------
    |
    | The date your previous business "ends" and this system begins.
    | Every opening balance journal entry is dated on this day.
    | No opening balance may be entered with a later date.
    |
    | Set this ONCE before entering any opening data. Changing it afterwards
    | requires reversing every opening entry.
    |
    */
    'cutoff_date' => env('SHOP_CUTOFF_DATE', \Illuminate\Support\Carbon::yesterday()->toDateString()),

    'name' => env('SHOP_NAME', 'আমার দোকান'),
    'currency' => env('SHOP_CURRENCY', '৳'),

    /*
    |--------------------------------------------------------------------------
    | Costing method
    |--------------------------------------------------------------------------
    | Only 'weighted_average' is implemented. FIFO would require a cost-layer
    | table; do not switch this without implementing that first.
    */
    'costing_method' => 'weighted_average',

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */
    'block_transactions_until_opening_locked' => true,
    'allow_negative_stock' => false,
];
