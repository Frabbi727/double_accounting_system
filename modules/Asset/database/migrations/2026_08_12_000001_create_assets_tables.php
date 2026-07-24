<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_bn');
            $table->string('name_en');

            // The chart account this category capitalizes into (Furniture -> 1510,
            // Advance Payment -> 1070, etc). Debited when an asset is acquired.
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_no')->unique();          // AST00001, id-derived
            $table->foreignId('asset_category_id')->constrained('asset_categories');
            $table->string('name');
            $table->date('purchase_date');

            // Acquisition cost — a document fact (like purchases.paid_amount).
            $table->decimal('amount', 18, 2);

            // account  -> paid now, credit a cash/bank account
            // credit   -> unpaid, credit Accounts Payable (2010), owed to supplier
            // opening  -> already owned at setup, credit Owner's Equity (3010)
            $table->string('payment_mode')->default('account');
            $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('vendor_name')->nullable();      // free-text vendor (non-master)

            $table->string('reference_no')->nullable();     // invoice / receipt number
            $table->string('description')->nullable();

            // Depreciation is deferred; columns left nullable for a later phase.
            $table->integer('useful_life_months')->nullable();
            $table->decimal('salvage_value', 18, 2)->nullable();

            // The balanced journal entry created for this acquisition.
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->string('status')->default('active');    // active | disposed
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disposed_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('purchase_date');
            $table->index(['supplier_id', 'payment_mode']);
        });

        Schema::create('asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
