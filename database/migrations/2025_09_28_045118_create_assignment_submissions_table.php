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
        Schema::create('assignment_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->longText('text_answer')->nullable();
            $t->string('file_path')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->unsignedInteger('score')->nullable();
            $t->unsignedTinyInteger('grade_5')->nullable();
            $t->enum('status',['submitted','returned','needs_fix'])->default('submitted');
            $t->text('teacher_comment')->nullable();
            $t->timestamps();
            $t->unique(['assignment_id','student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
