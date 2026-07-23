<?php

return [

    'sale_return_description' => 'বিক্রয় ফেরত :invoice',
    'sale_return_cogs_description' => 'বিক্রয় ফেরত (ক্রয়মূল্য) :invoice',
    'purchase_return_description' => 'ক্রয় ফেরত',
    'stock_loss_description' => 'স্টক ক্ষতি: :product',

    'errors' => [
        'no_items' => 'ফেরতে অন্তত একটি পণ্য থাকতে হবে।',
        'bad_qty' => 'ফেরতের পরিমাণ শূন্যের বেশি এবং মূল পরিমাণের মধ্যে হতে হবে।',
        'refund_exceeds' => 'ফেরতের অর্থ মোট মূল্যের চেয়ে বেশি হতে পারে না।',
    ],
];
