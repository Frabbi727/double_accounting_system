<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Per-line discount amount (FR-21). Posts to 4020 Sales Discount,
            // same as the bill-level sales.discount. Revenue is still recorded
            // gross; the discount is a separate debit.
            $table->decimal('discount', 18, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
