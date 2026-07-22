<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IMMUTABLE TABLE. Rows are never UPDATEd or DELETEd after creation.
        // Corrections are made by posting a reversing entry.
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('reference_type');       // 'Opening', 'Sale', 'Purchase'...
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description');

            // Reversal chain: if this entry was reversed, reversed_by_id points to the
            // reversing entry. On the reversing entry, reverses_id points back here.
            $table->foreignId('reversed_by_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reverses_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('date');
        });

        // IMMUTABLE TABLE.
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
