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
        Schema::create('grades', function (Blueprint $t) {
            $t->id();

            // Основные связи
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();

            // Polymorphic связь с источником оценки (QuizAttempt, AssignmentSubmission, etc)
            $t->nullableMorphs('gradeable'); // создаст gradeable_type и gradeable_id

            // Оценки
            $t->decimal('score', 8, 2)->nullable(); // баллы (например 85.50)
            $t->unsignedTinyInteger('grade_5')->nullable(); // оценка 2-5
            $t->unsignedInteger('max_points')->nullable(); // максимальный балл

            // Метаданные
            $t->string('title')->nullable(); // "Тест по кинематике", "Модульная оценка"
            $t->text('teacher_comment')->nullable();
            $t->timestamp('graded_at')->nullable();
            $t->timestamps();

            // Индексы для быстрой выборки
            $t->index(['student_id', 'course_id']);
            // nullableMorphs() уже создает индекс для gradeable_type и gradeable_id
            $t->index(['graded_at']);
            $t->index(['student_id', 'graded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
