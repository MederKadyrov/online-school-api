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
        Schema::create('module_assessments', function (Blueprint $t) {
            $t->id();

            $t->foreignId('module_id')->constrained()->cascadeOnDelete();

            // quiz | assignment | project ... (на будущее можно расширять)
            $t->string('kind')->default('quiz');

            // полиморфная цель (assessment entity)
            $t->string('assessable_type');
            $t->unsignedBigInteger('assessable_id');

            $t->timestamps();

            $t->unique(['module_id','kind']); // по одному каждого типа на модуль
            $t->index(['assessable_type','assessable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_assessments');
    }
};
