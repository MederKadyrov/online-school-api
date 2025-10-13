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
        Schema::create('module_grades', function (Blueprint $table) {
            $table->id();

            // Связи
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // Оценка
            $table->unsignedTinyInteger('grade_5')->nullable(); // 2, 3, 4, 5
            $table->text('teacher_comment')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            // Уникальность: один студент может получить только одну оценку за модуль
            $table->unique(['student_id', 'module_id']);

            // Индексы для быстрого поиска
            $table->index(['course_id', 'module_id']);
            $table->index('teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_grades');
    }
};
