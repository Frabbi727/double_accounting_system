<?php

return [

    // Stored on the journal entry description (suffix after "Model: name — ").
    'opening_balance' => 'Opening balance',
    'reversal_prefix' => 'Reversal',
    'corrected' => 'Corrected',

    'account_type' => [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'income' => 'Income',
        'expense' => 'Expense',
    ],

    'movement_type' => [
        'in' => 'Stock In',
        'out' => 'Stock Out',
        'adjustment' => 'Adjustment',
    ],

    'errors' => [
        'unbalanced' => 'Journal entry does not balance. Debit :debit, Credit :credit (difference :diff)',
        'period_locked' => 'This date (:date) falls before your business start date (the locked opening period), so no entry can be recorded here. The owner can fix it from the ‘Opening Balance’ page by setting the business start date.',
        'already_reversed' => 'This entry has already been reversed.',
        'zero_entry' => 'A zero-amount journal entry cannot be posted.',
        'line_both_sides' => 'Line #:line: a single line cannot have both a debit and a credit.',
        'line_no_side' => 'Line #:line: each line must have either a debit or a credit.',
        'line_negative' => 'Line #:line: a negative amount is not allowed.',
        'opening_positive' => 'The opening balance must be greater than zero.',
        'opening_already_posted' => 'An opening balance for this record (:model #:id) has already been posted. Reverse it if you need to correct it.',
        'opening_not_complete' => 'The opening balances are not locked yet. Complete and lock the opening before starting daily transactions.',
        'opening_cost_positive' => 'The opening stock cost must be greater than zero. Stock without a cost makes the profit figures completely wrong.',

        // Inventory
        'stock_in_qty' => 'The stock-in quantity must be greater than zero.',
        'stock_in_cost' => 'The stock-in cost must be greater than zero.',
        'stock_out_qty' => 'The stock-out quantity must be greater than zero.',
        'insufficient_stock' => 'Not enough stock for :product (available :available, requested :requested).',

        // Form validation
        'opening_locked_customer' => 'The opening balance period is locked. To add new dues, create a sale entry.',
        'opening_locked_product' => 'The opening period is locked. To add stock, create a purchase entry.',
        'opening_amount_gt' => 'The due amount must be greater than zero.',
        'opening_date_before_cutoff' => 'The due date cannot be later than the cut-off date (:cutoff).',
        'opening_cost_gt' => 'The cost price must be greater than zero. Stock without a cost makes profit figures wrong.',
        'opening_cost_required' => 'A cost price is required when adding stock.',
    ],

    'warnings' => [
        'sale_below_cost' => 'Warning: the sale price is lower than the cost price.',
    ],

    'reports' => [
        'current_profit' => 'Current period profit',
    ],
];
