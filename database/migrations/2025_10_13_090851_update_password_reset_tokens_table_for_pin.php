<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            // Удаляем старую структуру
            $table->dropPrimary();

            // Добавляем id и изменяем структуру
            $table->id()->first();
            $table->string('pin', 14)->after('id')->index();
            $table->string('email')->nullable()->change();
            $table->timestamp('expires_at')->nullable()->after('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn(['id', 'pin', 'expires_at']);
            $table->string('email')->nullable(false)->change();
            $table->primary('email');
        });
    }
};
