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
        Schema::create('modules', function (Blueprint $t) {
            $t->id();

            $t->foreignId('course_id')->constrained()->cascadeOnDelete();

            // number — логическая нумерация (1..4), position — для drag & drop
            $t->unsignedTinyInteger('number');
            $t->unsignedTinyInteger('position')->default(0);

            $t->string('title')->default(''); // опционально "Модуль 1"
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['course_id','number']);
            $t->index(['course_id','position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
