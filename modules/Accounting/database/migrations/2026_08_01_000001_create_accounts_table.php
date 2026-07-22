<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // 1010, 1021, 5020...

            // Bilingual name. The Account model exposes a locale-aware `name`
            // accessor over these two columns.
            $table->string('name_bn');
            $table->string('name_en');

            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->enum('subtype', [
                'cash', 'bank', 'receivable', 'payable',
                'inventory', 'loan', 'capital', 'other',
            ])->default('other');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();

            // is_system = seeded control accounts (1030 AR, 2010 AP, 1040 Inventory,
            // 3010 Owner's Equity). These must never be deleted or renamed by users.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'subtype']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
