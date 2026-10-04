<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor', 100);
            $table->string('role', 50)->default('User');
            $table->string('action', 50);
            $table->string('type', 50)->default('system');
            $table->string('information', 255)->default('N/A');
            $table->dateTime('datetime')->useCurrent();
        });

        Schema::create('background_images', function (Blueprint $table) {
            $table->id();
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('uploaded_by', 100);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('setting_key', 50)->primary();
            $table->string('setting_value', 255);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('background_images');
        Schema::dropIfExists('audit_logs');
    }
};
