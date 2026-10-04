<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id('borrower_id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('address', 100);
            $table->string('contact', 255);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->string('refnum', 50)->primary();
            $table->foreignId('borrower_id')->constrained('borrowers', 'borrower_id');
            $table->dateTime('dateBorrowed')->useCurrent();
            $table->dateTime('returnDate')->useCurrent();
            // pending -> borrowed -> returned | late | (auto-cancelled if unclaimed)
            $table->string('status', 50)->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('borrow_items', function (Blueprint $table) {
            $table->id();
            $table->string('refnum', 50);
            $table->foreignId('equipment_id')->nullable()->constrained('inventory', 'equipment_id')->nullOnDelete();
            $table->integer('quantity');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('refnum')->references('refnum')->on('transactions')->cascadeOnDelete();
        });

        Schema::create('return_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_item_id')->constrained('borrow_items', 'id')->restrictOnDelete();
            $table->integer('normal_qty')->default(0);
            $table->integer('broken_qty')->default(0);
            $table->integer('maintenance_qty')->default(0);
            $table->string('issue_status', 50)->default('partial');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_issues');
        Schema::dropIfExists('borrow_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('borrowers');
    }
};
