<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\Account;
use Modules\Asset\Models\AssetCategory;

/**
 * Seeds the common asset categories, each mapped to a fixed-asset (or current-
 * asset) chart account. The owner can add custom categories later; those default
 * to "Other Fixed Assets" (1560) unless another account is chosen.
 *
 * Must run AFTER ChartOfAccountsSeeder so the account codes exist.
 */
class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        // [name_bn, name_en, account_code, sort]
        $categories = [
            ['আসবাবপত্র',              'Furniture & Fixtures',   '1510', 10],
            ['অফিস সরঞ্জাম',           'Office Equipment',       '1520', 20],
            ['কম্পিউটার/ল্যাপটপ',      'Computer / Laptop',      '1530', 30],
            ['যানবাহন',                'Vehicle',                '1540', 40],
            ['জমি/ভবন',               'Land / Building',        '1550', 50],
            ['অগ্রিম প্রদান',           'Advance Payment',        '1070', 60],
            ['অন্যান্য সম্পদ',          'Other Asset',            '1560', 70],
        ];

        foreach ($categories as [$nameBn, $nameEn, $code, $sort]) {
            $accountId = Account::where('code', $code)->value('id');

            AssetCategory::updateOrCreate(
                ['name_en' => $nameEn],
                [
                    'name_bn' => $nameBn,
                    'account_id' => $accountId,
                    'is_system' => true,
                    'is_active' => true,
                    'sort' => $sort,
                ]
            );
        }
    }
}
