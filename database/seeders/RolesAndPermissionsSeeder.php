<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Three roles from requirements-document-bn.md §3.1:
 *   owner (মালিক)        — everything
 *   accountant (হিসাবরক্ষক) — all entries & reports, but no delete / user / opening
 *   salesperson (বিক্রয়কর্মী) — only sales entry & stock view; NEVER cost or profit
 *
 * The salesperson deliberately lacks `cost.view` and `report.view`: the owner's
 * hard rule (NFR-07) is that staff must not see cost price or profit anywhere.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'sale.create',      // create sale invoices
        'purchase.create',  // create purchase bills
        'expense.create',   // record expenses
        'payment.manage',   // customer/supplier payments, transfers
        'stock.view',       // see stock quantities
        'cost.view',        // see cost price & profit  (owner + accountant only)
        'report.view',      // see reports
        'master.manage',    // add/edit products, customers, suppliers, accounts
        'return.manage',    // create product returns    (owner + accountant)
        'asset.manage',     // add/manage fixed assets   (owner + accountant)
        'entry.delete',     // reverse/correct entries   (owner only)
        'user.manage',      // add/remove users          (owner only)
        'opening.manage',   // enter/lock opening balances (owner only)
        'backup.manage',    // download data backups      (owner only)
    ];

    public const ROLE_PERMISSIONS = [
        'owner' => self::PERMISSIONS,   // all
        'accountant' => [
            'sale.create', 'purchase.create', 'expense.create', 'payment.manage',
            'stock.view', 'cost.view', 'report.view', 'master.manage', 'return.manage',
            'asset.manage',
        ],
        'salesperson' => [
            'sale.create', 'stock.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Reload so the freshly-created permissions are resolvable by name below.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
