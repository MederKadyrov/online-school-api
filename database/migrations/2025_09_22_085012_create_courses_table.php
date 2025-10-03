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
        Schema::create('courses', function (Blueprint $t) {
            $t->id();

            // FK: предмет, преподаватель, уровень
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $t->foreignId('level_id')->constrained('levels')->cascadeOnDelete();

            $t->string('title');           // например: "Физика, 7 класс"
            $t->string('slug')->unique();  // fizika-7-klass

            $t->enum('status', ['draft','published','archived'])->default('draft');
            $t->timestamp('published_at')->nullable();

            $t->timestamps();
            $t->softDeletes();

            // (опционально) снять дубли у одного препода на одном предмете и уровне:
             $t->unique(['teacher_id','subject_id','level_id'], 'courses_teacher_subject_level_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
