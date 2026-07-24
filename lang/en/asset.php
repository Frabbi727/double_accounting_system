<?php

return [

    'list_title' => 'Asset Register',
    'details_title' => 'Asset Details',
    'new' => 'New Asset',
    'edit_title' => 'Edit Asset',
    'save' => 'Save Asset',
    'update' => 'Update',

    // Voucher description (English class-basename-safe if ever routed to opening).
    'description' => 'Asset: :name (:no)',

    // --- List columns ---
    'asset_no' => 'Asset No',
    'name' => 'Asset Name',
    'category' => 'Category',
    'purchase_date' => 'Purchase Date',
    'amount' => 'Amount',
    'current_value' => 'Current Value',
    'status' => 'Status',
    'status_active' => 'Active',
    'status_disposed' => 'Disposed',
    'active_total' => 'Total active asset value',
    'empty' => 'No assets recorded yet.',

    // --- Form ---
    'info' => 'Asset Information',
    'purchase_details' => 'Purchase Details',
    'payment_info' => 'Payment Information',
    'vendor' => 'Purchased From (Vendor/Supplier)',
    'vendor_name' => 'Vendor name',
    'vendor_hint' => 'A supplier from your list, or type any vendor name.',
    'supplier' => 'Supplier',
    'reference_no' => 'Reference / Invoice No',
    'description_label' => 'Description / Notes',
    'documents' => 'Supporting Documents',
    'documents_hint' => 'Invoice, receipt, warranty or image (JPG/PNG/PDF, max 4MB each).',
    'add_document' => 'Add document',
    'no_documents' => 'No documents attached.',
    'add_category' => '+ Add category',
    'category_name_bn' => 'Category name (Bangla)',
    'category_name_en' => 'Category name (English)',

    // --- Payment modes ---
    'payment_mode' => 'How was it paid?',
    'mode_account' => 'Paid from an account',
    'mode_account_hint' => 'Debit the asset, credit the Cash / Bank / Mobile-banking account.',
    'mode_credit' => 'On credit (unpaid)',
    'mode_credit_hint' => 'Debit the asset, credit Accounts Payable — raises the supplier’s due.',
    'mode_opening' => 'Already owned (opening)',
    'mode_opening_hint' => 'An asset the shop already owned at setup — credit Owner’s Equity. Opening period only.',
    'payment_account' => 'Payment account',
    'remaining_due' => 'Remaining due',
    'due_note' => 'This is the supplier’s current total due; pay it from the Payments screen.',
    'view_supplier' => 'View supplier',

    // --- Detail page ---
    'voucher' => 'Accounting Entry / Voucher',
    'voucher_no' => 'Voucher No',
    'entry_date' => 'Entry date',
    'account' => 'Account',
    'debit' => 'Debit',
    'credit' => 'Credit',
    'reversal_chain' => 'Reversal (disposal)',
    'audit' => 'Audit Log',
    'created_by' => 'Created by',
    'created_at' => 'Created at',
    'disposed_by' => 'Disposed by',
    'disposed_at' => 'Disposed at',
    'disposed_reason' => 'Disposal reason',
    'download' => 'Download',
    'edit' => 'Edit',
    'details' => 'Details',

    // --- Dispose ---
    'dispose' => 'Dispose asset',
    'dispose_reason' => 'Disposal reason',
    'dispose_hint' => 'Writing off / selling / discarding this asset reverses its accounting entry.',
    'confirm_dispose' => 'Dispose this asset? Its accounting entry will be reversed.',
    'disposed_banner' => 'This asset was disposed by :by on :at.',

    // --- Confirmation dialog ---
    'confirm_title' => 'Confirm this asset',
    'confirm_intro' => 'Please check the details below. On save, the asset is recorded and the accounting entry is posted.',
    'confirm_entry' => 'Accounting entry that will be posted',
    'confirm_debit' => 'Debit',
    'confirm_credit' => 'Credit',
    'confirm_documents' => 'Documents attached',
    'confirm_back' => 'Go back',
    'confirm_yes' => 'Confirm & Save',
    'owner_equity' => 'Owner’s Equity (already owned)',
    'accounts_payable' => 'Accounts Payable',

    'categories' => [
        'title' => 'Asset Categories',
        'account' => 'Asset Account',
        'system' => 'Built-in',
        'in_use' => 'This category is in use or built-in and cannot be deleted.',
        'delete_confirm' => 'Delete this category?',
    ],

    'errors' => [
        'amount_positive' => 'The amount must be greater than zero.',
        'category_no_account' => 'This asset category has no linked account.',
        'credit_needs_supplier' => 'Select a supplier for an on-credit asset.',
        'account_needs_payment' => 'Select the account the asset was paid from.',
        'already_disposed' => 'This asset has already been disposed.',
    ],
];
