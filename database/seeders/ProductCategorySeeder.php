<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    /**
     * Seed a starter tree of categories with sub-categories. Idempotent
     * (firstOrCreate) so it is safe to re-run on a live database. The user
     * can add/remove/rename any of these from the Category manage screen.
     */
    public function run(): void
    {
        $tree = [
            ['মুদি', 'Grocery', ['চাল/ডাল' => 'Rice & Lentils', 'তেল/মসলা' => 'Oil & Spices', 'চিনি/লবণ' => 'Sugar & Salt']],
            ['পানীয়', 'Beverage', ['কোমল পানীয়' => 'Soft Drinks', 'চা/কফি' => 'Tea & Coffee', 'পানি' => 'Water']],
            ['স্ন্যাকস', 'Snacks', ['বিস্কুট' => 'Biscuits', 'চিপস' => 'Chips', 'চকলেট' => 'Chocolate']],
            ['স্টেশনারি', 'Stationery', ['খাতা/কলম' => 'Notebooks & Pens', 'কাগজ' => 'Paper']],
            ['গৃহস্থালি', 'Household', ['পরিষ্কারক' => 'Cleaning', 'টয়লেট্রিজ' => 'Toiletries']],
        ];

        foreach ($tree as [$bn, $en, $children]) {
            $parent = ProductCategory::firstOrCreate(
                ['name_en' => $en, 'parent_id' => null],
                ['name_bn' => $bn],
            );

            foreach ($children as $childBn => $childEn) {
                ProductCategory::firstOrCreate(
                    ['name_en' => $childEn, 'parent_id' => $parent->id],
                    ['name_bn' => $childBn],
                );
            }
        }
    }
}
