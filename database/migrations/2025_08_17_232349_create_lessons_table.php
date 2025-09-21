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
        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('room')->nullable();
            $t->string('meeting_url')->nullable();
            $t->enum('meeting_provider', ['jitsi','bbb','zoom','meet','custom'])->nullable();
            $t->timestamps();
            $t->unique(['subject_id','teacher_id','group_id','starts_at']);
            $t->index(['starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
