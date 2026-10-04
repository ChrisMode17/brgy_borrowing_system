<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('category_name', 50);
        });

        Schema::create('inventory', function (Blueprint $table) {
            $table->id('equipment_id');
            $table->string('name', 100);
            $table->foreignId('category_id')->constrained('inventory_categories', 'category_id');
            $table->integer('total_quantity');
            // Links a "maintenance twin" item back to the original item it split off from
            $table->unsignedBigInteger('source_equipment_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('source_equipment_id')->references('equipment_id')->on('inventory')->nullOnDelete();
        });

        Schema::create('inventory_status', function (Blueprint $table) {
            $table->foreignId('equipment_id')->constrained('inventory', 'equipment_id')->cascadeOnDelete();
            $table->enum('status', ['available', 'under_maintenance', 'broken', 'lost']);
            $table->integer('quantity')->default(0);

            $table->primary(['equipment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_status');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('inventory_categories');
    }
};
