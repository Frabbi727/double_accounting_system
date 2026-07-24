<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business record for every incentive / rebate given to or received from a
 * party (FR-49/50/53). This table holds the *metadata* only — who, what kind,
 * on what basis, at what rate. The financial truth (and the "how much is
 * settled / how much is still due") stays in the immutable ledger: each row
 * links to the journal entry it posted, and — when settled against a party's
 * due — that entry touches the AR/AP control account, so the remaining due
 * is always derived via ReportService::partyDue(), never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_incentives', function (Blueprint $table) {
            $table->id();

            // incentive = conditional bonus (income/expense); rebate = post-purchase
            // discount that lowers inventory cost.
            $table->enum('kind', ['incentive', 'rebate']);

            // received (from a supplier — inflow) / given (to a customer — outflow).
            $table->enum('direction', ['received', 'given']);

            // Simple string attribution ('customer' | 'supplier') matching
            // ReportService::partyDue($party, $id). Nullable: a rebate may target
            // only a product with no party attribution.
            $table->string('party_type')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();

            // How the amount was arrived at.
            $table->enum('basis', [
                'fixed',                 // flat cash amount
                'pct_of_due',            // % of the party's current due
                'pct_of_invoice',        // % of one sale/purchase document total
                'pct_of_product_value',  // % of a product's on-hand stock value
                'pct_of_sales',          // "sell %": % of period turnover with the party
            ]);
            $table->decimal('rate', 5, 2)->nullable();        // percentage, when basis != fixed
            $table->decimal('base_amount', 18, 2)->nullable(); // the computed base (audit)
            $table->decimal('amount', 18, 2);                  // final posted amount

            // Rebate target product (weighted-avg cost lowered).
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Source document for pct_of_invoice ('Sale' | 'Purchase').
            $table->string('ref_doc_type')->nullable();
            $table->unsignedBigInteger('ref_doc_id')->nullable();

            // cash = paid/received in cash/bank; due = netted against the party's AR/AP.
            $table->enum('settle_mode', ['cash', 'due']);
            $table->foreignId('settle_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();     // cash/bank account when settle_mode = cash

            // Window for pct_of_sales.
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();

            $table->date('date');
            $table->string('notes')->nullable();

            // The ledger entry this record posted.
            $table->foreignId('journal_entry_id')->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['party_type', 'party_id']);
            $table->index(['kind', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_incentives');
    }
};
