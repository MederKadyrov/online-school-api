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
        Schema::create('quiz_answers', function (Blueprint $t) {
            $t->id();

            $t->foreignId('attempt_id')
                ->constrained('quiz_attempts') // quiz_attempts.id
                ->cascadeOnDelete();

            $t->foreignId('question_id')
                ->constrained('quiz_questions') // quiz_questions.id
                ->cascadeOnDelete();

            // Для single/multiple: массив id опций; для text: null
            $t->json('selected_option_ids')->nullable();

            // Для text: ответ свободной формы
            $t->text('text_answer')->nullable();

            // Авто-оценка и ручная корректировка
            $t->float('auto_score')->nullable();
            $t->float('manual_adjustment')->nullable();

            $t->timestamps();

            // Одна запись ответа на вопрос в рамках одной попытки
            $t->unique(['attempt_id','question_id']);

            $t->index(['attempt_id']);
            $t->index(['question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
