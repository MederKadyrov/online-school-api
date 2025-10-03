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
        Schema::create('quiz_attempts', function (Blueprint $t) {
            $t->id();

            $t->foreignId('quiz_id')
                ->constrained() // quizzes.id
                ->cascadeOnDelete();

            $t->foreignId('student_id')
                ->constrained() // students.id
                ->cascadeOnDelete();

            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();

            $t->float('score')->default(0);
            $t->unsignedTinyInteger('grade_5')->nullable();

            $t->boolean('autograded')->default(false);
            $t->enum('status', ['in_progress','submitted','graded'])->default('in_progress');

            $t->timestamps();

            $t->index(['quiz_id','student_id','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
