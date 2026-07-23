<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_no')->nullable();
            $table->date('date');

            // Overall discount given on this sale (posts to 4020 Sales Discount).
            $table->decimal('discount', 18, 2)->default(0);

            // Amount collected now. The remainder becomes receivable and is
            // tracked in the ledger (1030), NOT stored as a due column here.
            $table->decimal('paid_amount', 18, 2)->default(0);

            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index(['customer_id', 'date']);

            // NOTE: deliberately NO subtotal / total / due / profit columns — all derived.
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('qty', 18, 3);
            $table->decimal('unit_price', 18, 2);

            // FROZEN cost at sale time — the single source of historical COGS.
            $table->decimal('cost_price', 18, 4);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
