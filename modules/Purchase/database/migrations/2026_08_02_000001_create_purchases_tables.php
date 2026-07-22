<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('invoice_no')->nullable();
            $table->date('date');

            // Extra costs (freight, duty) capitalized into inventory and
            // apportioned across the line items by value.
            $table->decimal('landed_cost', 18, 2)->default(0);

            // Amount paid at purchase time. The remainder becomes payable and
            // is tracked in the ledger (2010), NOT stored as a due column here.
            $table->decimal('paid_amount', 18, 2)->default(0);

            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index(['supplier_id', 'date']);

            // NOTE: deliberately NO subtotal / total / due columns — all derived.
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('qty', 18, 3);
            $table->decimal('unit_cost', 18, 4);   // invoice cost, before landed apportionment
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
