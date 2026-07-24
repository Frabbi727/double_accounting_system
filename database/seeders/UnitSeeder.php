<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\Unit;

class UnitSeeder extends Seeder
{
    /**
     * Seed common shop units. These only populate the product-form datalist —
     * the user can still type any unit, and can manage this list later.
     * Idempotent (firstOrCreate) so it is safe to re-run on a live database.
     */
    public function run(): void
    {
        $units = [
            ['পিস', 'pcs'],
            ['কেজি', 'kg'],
            ['গ্রাম', 'gram'],
            ['লিটার', 'litre'],
            ['মিলি', 'ml'],
            ['ডজন', 'dozen'],
            ['বক্স', 'box'],
            ['প্যাকেট', 'packet'],
            ['বস্তা', 'bag'],
            ['হালি', 'hali'],
        ];

        foreach ($units as [$bn, $en]) {
            Unit::firstOrCreate(['name_en' => $en], ['name_bn' => $bn]);
        }
    }
}
