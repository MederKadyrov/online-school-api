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
        Schema::create('groups', function (Blueprint $t) {
            $t->id();
            $t->char('class_letter', 2)->nullable();

            $t->foreignId('level_id')
                ->constrained('levels')
                ->restrictOnDelete();

            $t->foreignId('homeroom_teacher_id')
                ->nullable()
                ->constrained('teachers')   // references teachers.id
                ->nullOnDelete();           // при удалении учителя поле обнуляется

            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
