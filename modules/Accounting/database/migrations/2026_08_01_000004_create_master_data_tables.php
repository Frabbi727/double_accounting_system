<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();

            // Bilingual name. ProductCategory exposes a locale-aware `name` accessor.
            $table->string('name_bn');
            $table->string('name_en');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->index();   // lower(trim(name)) — duplicate detection
            $table->string('sku')->nullable()->unique();
            $table->foreignId('product_category_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('unit')->default('pcs');

            // Weighted-average cost. Recalculated by CostingService on every stock IN.
            // NOT a user-editable field after opening.
            $table->decimal('cost_price', 18, 4)->default(0);
            $table->decimal('sale_price', 18, 2)->default(0);

            $table->integer('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // NOTE: deliberately NO opening_qty column and NO current_stock column.
            // Stock is always derived from stock_movements.
        });

        // IMMUTABLE TABLE. Never UPDATE or DELETE. Corrections = reversing movement.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->decimal('qty', 18, 3);               // signed: +in, -out
            $table->decimal('unit_cost', 18, 4)->nullable();  // required on 'in'
            $table->string('reference_type');            // 'Opening','Purchase','Sale'...
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'date']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->index();
            $table->string('phone')->nullable()->unique();
            $table->string('address')->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);   // 0 = no limit
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // NOTE: deliberately NO opening_balance column.
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->index();
            $table->string('phone')->nullable()->unique();
            $table->string('address')->nullable();
            $table->integer('payment_term_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // NOTE: deliberately NO opening_balance column.
        });

        // Subsidiary detail behind the AR/AP control accounts.
        Schema::create('opening_party_balances', function (Blueprint $table) {
            $table->id();
            $table->morphs('party');                     // Customer / Supplier
            $table->decimal('amount', 18, 2);
            $table->date('original_date');               // real age of the debt (for aging)
            $table->string('reference')->nullable();     // old invoice no
            $table->foreignId('journal_entry_id')->constrained();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_party_balances');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
