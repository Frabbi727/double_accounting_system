<?php

return [

    'expense_description' => 'খরচ: :account',
    'payment_in_description' => 'পেমেন্ট গ্রহণ: :party',
    'payment_out_description' => 'পেমেন্ট প্রদান: :party',
    'transfer_description' => 'স্থানান্তর: :from → :to',

    'errors' => [
        'amount_positive' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
        'exceeds_due' => 'পরিমাণ বর্তমান বাকির (:due) চেয়ে বেশি হতে পারে না।',
        'insufficient_balance' => ':account অ্যাকাউন্টে যথেষ্ট টাকা নেই (আছে :balance)।',
        'not_expense_account' => 'নির্বাচিত অ্যাকাউন্টটি খরচের অ্যাকাউন্ট নয়।',
        'same_account' => 'একই অ্যাকাউন্টে স্থানান্তর করা যাবে না।',
    ],
];
