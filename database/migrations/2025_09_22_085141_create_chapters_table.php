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
        Schema::create('chapters', function (Blueprint $t) {
            $t->id();

            $t->foreignId('module_id')->constrained()->cascadeOnDelete();

            $t->unsignedSmallInteger('number');   // 1,2,3...
            $t->unsignedSmallInteger('position')->default(0);

            $t->string('title');
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['module_id','number']);
            $t->index(['module_id','position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
