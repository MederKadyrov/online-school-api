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
        Schema::create('student_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->string('guardian_application_path')->nullable();   // Заявление
            $t->string('birth_certificate_path')->nullable();      // Св-во о рождении
            $t->string('student_pin_doc_path')->nullable();        // Документ с PIN
            $t->string('guardian_passport_path')->nullable();      // Паспорт родителя/представителя
            $t->string('medical_certificate_path')->nullable();    // Мед. справка
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
