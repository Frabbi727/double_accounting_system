<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data backup (FR-72). Owner only (backup.manage). Produces a portable,
 * driver-agnostic JSON snapshot of every business table that the owner can
 * download and keep off-site — no external dump tool required.
 */
class BackupController extends Controller
{
    /** Business tables to include, in dependency-friendly order. */
    private const TABLES = [
        'accounting_periods', 'accounts', 'product_categories', 'products',
        'customers', 'suppliers', 'opening_party_balances',
        'journal_entries', 'journal_entry_lines', 'stock_movements',
        'sales', 'sale_items', 'purchases', 'purchase_items',
        'settings', 'users',
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
    ];

    public function index()
    {
        return view('shop.settings.backup');
    }

    public function download(): StreamedResponse
    {
        $filename = 'backup-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(function () {
            $tables = array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));

            echo '{"generated_at":'.json_encode(now()->toIso8601String());
            echo ',"app":'.json_encode(config('app.name'));
            echo ',"tables":{';
            foreach ($tables as $i => $table) {
                echo ($i ? ',' : '').json_encode($table).':';
                echo json_encode(DB::table($table)->get());
            }
            echo '}}';
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
