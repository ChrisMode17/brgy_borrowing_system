<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('email', 100)->unique();
            $table->string('contact_number', 25);
            $table->foreignId('role_id')->constrained('roles', 'role_id');
            // Access level hierarchy: 0 = Admin, 1 = Semi-Admin, 2 = Moderator, 3 = User (lower = more privileged)
            $table->unsignedTinyInteger('access_level');
            $table->timestamp('created_at')->useCurrent();
            $table->rememberToken();
        });

        Schema::create('user_security_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('question');
            $table->string('answer_hash');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_security_answers');
        Schema::dropIfExists('users');
    }
};
