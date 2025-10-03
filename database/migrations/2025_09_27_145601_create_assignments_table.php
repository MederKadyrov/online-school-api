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
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();

            // Один assignment на один paragraph
            $t->foreignId('paragraph_id')
                ->constrained()               // paragraphs.id
                ->cascadeOnDelete();
            $t->unique('paragraph_id');

            $t->string('title', 150);
            $t->longText('instructions')->nullable();

            // дедлайн (опционально)
            $t->timestamp('due_at')->nullable();

            // максимум баллов (по умолчанию 100)
            $t->unsignedInteger('max_points')->default(100);

            // путь к файлу-условию (если есть), храним относительный путь (disk=public)
            $t->string('attachments_path')->nullable();

            // статус публикации
            $t->enum('status', ['draft', 'published'])->default('draft');

            $t->timestamps();

            // полезные индексы
            $t->index(['status']);
            $t->index(['paragraph_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
