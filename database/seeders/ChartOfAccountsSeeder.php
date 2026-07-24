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
     * Columns: [code, name_bn, name_en, type, subtype, is_system, parent_code?]
     *
     * The optional 7th element is a parent account's code; parent_id is resolved
     * in a second pass after every account exists (so order does not matter).
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
            ['1070', 'অগ্রিম প্রদান',                 'Advance Payments',    'asset',     'other',      true],

            // ---- Fixed Assets (capitalized purchases via the Asset module) ----
            // subtype stays 'other' — the accounts DB enum has no fixed_asset value;
            // grouping is by the 1500 parent, not the subtype.
            ['1500', 'স্থায়ী সম্পদ',                  'Fixed Assets',            'asset', 'other', true],
            ['1510', 'আসবাবপত্র',                     'Furniture & Fixtures',    'asset', 'other', false, '1500'],
            ['1520', 'অফিস সরঞ্জাম',                  'Office Equipment',        'asset', 'other', false, '1500'],
            ['1530', 'কম্পিউটার ও ইলেকট্রনিক্স',      'Computer & Electronics',  'asset', 'other', false, '1500'],
            ['1540', 'যানবাহন',                       'Vehicles',                'asset', 'other', false, '1500'],
            ['1550', 'জমি ও ভবন',                     'Land & Building',         'asset', 'other', false, '1500'],
            ['1560', 'অন্যান্য স্থায়ী সম্পদ',          'Other Fixed Assets',      'asset', 'other', false, '1500'],

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
            ['4040', 'ফেরত কর্তন আয়',              'Return Deduction Income', 'income', 'other',     true],

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

        foreach ($accounts as $row) {
            [$code, $nameBn, $nameEn, $type, $subtype, $isSystem] = $row;

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

        // Second pass: link children to their parent account now that all exist.
        foreach ($accounts as $row) {
            $parentCode = $row[6] ?? null;
            if ($parentCode === null) {
                continue;
            }
            $parentId = Account::where('code', $parentCode)->value('id');
            Account::where('code', $row[0])->update(['parent_id' => $parentId]);
        }
    }
}
