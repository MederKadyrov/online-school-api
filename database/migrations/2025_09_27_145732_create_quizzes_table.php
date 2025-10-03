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
        Schema::create('quizzes', function (Blueprint $t) {
            $t->id();

            // Один quiz на один paragraph
            $t->foreignId('paragraph_id')
                ->constrained()               // paragraphs.id
                ->cascadeOnDelete();
            $t->unique('paragraph_id');

            $t->string('title', 150);
            $t->longText('instructions')->nullable();

            // ограничения/настройки прохождения
            $t->unsignedInteger('time_limit_sec')->nullable();   // лимит времени (сек), null = без лимита
            $t->unsignedTinyInteger('max_attempts')->nullable(); // лимит попыток, null = без лимита
            $t->boolean('shuffle')->default(false);              // перемешивание вопросов/вариантов (базовый флаг)

            // статус публикации
            $t->enum('status', ['draft', 'published'])->default('draft');

            // максимальный балл за тест.
            // можно хранить явно (фиксируется при публикации как сумма points по вопросам),
            // чтобы не пересчитывать на лету.
            $t->unsignedInteger('max_points')->default(0);

            $t->timestamps();

            // индексы
            $t->index(['status']);
            $t->index(['paragraph_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
