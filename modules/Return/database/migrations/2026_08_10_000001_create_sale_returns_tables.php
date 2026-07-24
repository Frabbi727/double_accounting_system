<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();

            // Human-facing return number (SR00001), generated post-insert.
            $table->string('return_no')->nullable()->unique();

            // The original invoice this return is made against.
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();

            // Snapshot of the sale's customer for fast list queries (walk-in = null).
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->date('date');
            $table->string('reason')->nullable();
            $table->string('notes')->nullable();

            // Refund deduction (restocking/handling charge kept by the shop).
            // The RAW input; the money effect lives only in the ledger.
            $table->string('deduction_type')->nullable();          // none | fixed | percent
            $table->decimal('deduction_value', 18, 2)->default(0);

            // Account the refund was paid from (Cash/Bank/MFS).
            $table->foreignId('refund_account_id')->constrained('accounts');

            // The invoice-discount policy applied when this return was created.
            $table->string('discount_policy')->default('ignore');  // ignore | proportional

            $table->string('status')->default('completed');        // completed | cancelled

            // Links back to the posted journal entries (for details + cancel).
            $table->foreignId('revenue_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('cogs_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index(['customer_id', 'date']);
            $table->index('sale_id');
            $table->index('status');

            // NOTE: deliberately NO total_refund / total_deduction columns — the
            // money effects are read back from the linked journal entries.
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products');

            $table->decimal('qty', 18, 3);

            // Snapshots taken at return time so the return document is stable even
            // if the source sale line is ever touched.
            $table->decimal('unit_price', 18, 2);   // snapshot of sale_items.unit_price
            $table->decimal('cost_price', 18, 4);   // snapshot of the frozen sale_items.cost_price

            $table->timestamps();

            $table->index('sale_item_id');   // the cumulative-return guard queries this
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
