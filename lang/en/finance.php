<?php

return [

    'expense_description' => 'Expense: :account',
    'payment_in_description' => 'Payment received: :party',
    'payment_out_description' => 'Payment made: :party',
    'transfer_description' => 'Transfer: :from → :to',

    'errors' => [
        'amount_positive' => 'The amount must be greater than zero.',
        'exceeds_due' => 'The amount cannot exceed the current due (:due).',
        'insufficient_balance' => 'Not enough balance in :account (available :balance).',
        'not_expense_account' => 'The selected account is not an expense account.',
        'same_account' => 'Cannot transfer to the same account.',
    ],
];
