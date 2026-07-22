<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\Account;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Accounts marked is_system = true are referenced by code elsewhere in the
     * application (OpeningEntryService, SaleService, PurchaseService).
     * They must never be renamed, re-coded or deleted.
     *
     * Columns: [code, name_bn, name_en, type, subtype, is_system]
     */
    public function run(): void
    {
        $accounts = [
            // ---- Assets ----
            ['1000', 'সম্পদ',                        'Assets',              'asset',     'other',      true],
            ['1010', 'ক্যাশ ইন হ্যান্ড',              'Cash in Hand',        'asset',     'cash',       true],
            ['1021', 'ব্যাংক অ্যাকাউন্ট',             'Bank Account',        'asset',     'bank',       false],
            ['1030', 'কাস্টমার বাকি (AR)',           'Accounts Receivable', 'asset',     'receivable', true],
            ['1040', 'ইনভেন্টরি (মজুদ)',             'Inventory',           'asset',     'inventory',  true],

            // ---- Liabilities ----
            ['2000', 'দায়',                          'Liabilities',         'liability', 'other',      true],
            ['2010', 'সাপ্লায়ার বাকি (AP)',          'Accounts Payable',    'liability', 'payable',    true],
            ['2020', 'ব্যাংক লোন',                    'Bank Loan',           'liability', 'loan',       false],

            // ---- Equity ----
            ['3000', 'পুঁজি',                        'Equity',              'equity',    'other',      true],
            ['3010', 'মালিকের পুঁজি',                "Owner's Equity",      'equity',    'capital',    true],
            ['3020', 'সঞ্চিত মুনাফা',                'Retained Earnings',   'equity',    'capital',    true],

            // ---- Income ----
            ['4000', 'আয়',                          'Income',              'income',    'other',      true],
            ['4010', 'বিক্রয় আয়',                   'Sales Revenue',       'income',    'other',      true],
            ['4020', 'বিক্রয় ছাড়',                  'Sales Discount',      'income',    'other',      true],
            ['4030', 'ইনসেন্টিভ আয়',                'Incentive Income',    'income',    'other',      false],

            // ---- Expenses ----
            ['5000', 'খরচ',                          'Expenses',            'expense',   'other',      true],
            ['5010', 'বিক্রিত পণ্যের ক্রয়মূল্য (COGS)', 'Cost of Goods Sold', 'expense',  'other',      true],
            ['5020', 'দোকান ভাড়া',                   'Shop Rent',           'expense',   'other',      false],
            ['5030', 'কর্মচারী বেতন',                'Staff Salary',        'expense',   'other',      false],
            ['5040', 'বিদ্যুৎ বিল',                  'Electricity Bill',    'expense',   'other',      false],
            ['5050', 'পরিবহন খরচ',                   'Transport Cost',      'expense',   'other',      false],
            ['5060', 'মোবাইল ও ইন্টারনেট',          'Mobile & Internet',   'expense',   'other',      false],
            ['5070', 'মনিহারি',                      'Stationery',          'expense',   'other',      false],
            ['5080', 'মেরামত',                       'Repairs',             'expense',   'other',      false],
            ['5090', 'ব্যাংক চার্জ',                 'Bank Charges',        'expense',   'other',      false],
            ['5100', 'ইনসেন্টিভ খরচ',                'Incentive Expense',   'expense',   'other',      false],
            ['5110', 'স্টক ক্ষতি (নষ্ট/চুরি)',        'Stock Loss',          'expense',   'other',      true],
            ['5900', 'অন্যান্য খরচ',                 'Other Expenses',      'expense',   'other',      false],
        ];

        foreach ($accounts as [$code, $nameBn, $nameEn, $type, $subtype, $isSystem]) {
            Account::updateOrCreate(
                ['code' => $code],
                [
                    'name_bn' => $nameBn,
                    'name_en' => $nameEn,
                    'type' => $type,
                    'subtype' => $subtype,
                    'is_system' => $isSystem,
                    'is_active' => true,
                ]
            );
        }
    }
}
