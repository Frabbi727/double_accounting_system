<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database: chart of accounts, roles, and one demo
     * user per role so the app is usable straight after a fresh migrate.
     */
    public function run(): void
    {
        $this->call([
            ChartOfAccountsSeeder::class,
            RolesAndPermissionsSeeder::class,
            ProductCategorySeeder::class,
            UnitSeeder::class,
            AssetCategorySeeder::class,
        ]);

        $demo = [
            ['মালিক (Owner)', 'owner@shop.test', 'owner'],
            ['হিসাবরক্ষক (Accountant)', 'accountant@shop.test', 'accountant'],
            ['বিক্রয়কর্মী (Salesperson)', 'sales@shop.test', 'salesperson'],
        ];

        foreach ($demo as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            $user->syncRoles([$role]);
        }
    }
}
