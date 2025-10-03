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
        Schema::create('quiz_questions', function (Blueprint $t) {
            $t->id();

            $t->foreignId('quiz_id')
                ->constrained() // quizzes.id
                ->cascadeOnDelete();

            $t->enum('type', ['single','multiple','text']);
            $t->text('text');

            $t->unsignedInteger('points')->default(1);
            $t->unsignedInteger('position')->default(0); // для dnd/упорядочивания

            $t->timestamps();

            $t->index(['quiz_id','position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
