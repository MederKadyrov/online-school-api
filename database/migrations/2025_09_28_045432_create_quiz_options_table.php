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
        Schema::create('quiz_options', function (Blueprint $t) {
            $t->id();

            $t->foreignId('question_id')
                ->constrained('quiz_questions') // quiz_questions.id
                ->cascadeOnDelete();

            $t->text('text');
            $t->boolean('is_correct')->default(false);
            $t->unsignedInteger('position')->default(0);

            $t->timestamps();

            $t->index(['question_id','position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_options');
    }
};
