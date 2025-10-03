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
        Schema::create('resources', function (Blueprint $t) {
            $t->id();

            $t->foreignId('paragraph_id')->constrained()->cascadeOnDelete();

            $t->enum('type', ['video','file','link','presentation','text']);
            $t->string('title')->nullable();

            $t->string('url')->nullable();         // внешняя ссылка/встраивание
            $t->string('path')->nullable();        // storage путь
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size_bytes')->nullable();
            $t->string('external_provider')->nullable(); // youtube, vimeo...

            $t->longText('text_content')->nullable();
            $t->unsignedInteger('duration_sec')->nullable();

            $t->unsignedSmallInteger('position')->default(0);

            $t->timestamps();
            $t->softDeletes();

            $t->index(['paragraph_id','position']);
            $t->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
