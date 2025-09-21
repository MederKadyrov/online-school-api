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
            $t->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete(); // кто поставил
            $t->unsignedTinyInteger('value'); // 1..10 (или 1..5 — на твой вкус)
            $t->string('comment')->nullable();
            $t->timestamp('graded_at')->nullable();
            $t->timestamps();
            $t->unique(['lesson_id','student_id']); // по одному итоговому баллу за урок
            $t->index(['student_id','graded_at']);
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
