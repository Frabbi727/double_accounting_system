<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-referential parent → a category (parent_id null) can hold
        // sub-categories (parent_id set). Single level only.
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('product_categories')->nullOnDelete();
        });

        // Unit master — only the SOURCE for the product-form datalist and the
        // manage screen. The chosen unit is still stored as the string
        // products.unit (no FK), so Purchase/Sale/Report code is untouched.
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name_bn');
            $table->string('name_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
