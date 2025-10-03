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
        Schema::create('course_group', function (Blueprint $t) {
            $t->id();

            $t->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $t->foreignId('group_id')->constrained('groups')->cascadeOnDelete();

            $t->timestamps();

            // один и тот же курс можно назначить сразу нескольким группам,
            // но одна и та же пара course+group — уникальна:
            $t->unique(['course_id','group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_group');
    }
};
