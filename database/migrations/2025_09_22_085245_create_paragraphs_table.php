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
        Schema::create('paragraphs', function (Blueprint $t) {
            $t->id();

            $t->foreignId('chapter_id')->constrained()->cascadeOnDelete();

            $t->unsignedSmallInteger('number');   // 1,2,3...
            $t->unsignedSmallInteger('position')->default(0);

            $t->string('title');
            $t->text('description')->nullable(); // краткое описание/цель
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['chapter_id','number']);
            $t->index(['chapter_id','position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paragraphs');
    }
};
