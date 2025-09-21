<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('students', function (Blueprint $t) {
            if (!Schema::hasColumn('students','group_id')) {
                $t->foreignId('group_id')
                    ->nullable()
                    ->after('level_id')
                    ->constrained('groups')
                    ->nullOnDelete(); // если группу удалили — отвяжем студента
            }
        });
    }
    public function down(): void {
        Schema::table('students', function (Blueprint $t) {
            if (Schema::hasColumn('students','group_id')) {
                $t->dropConstrainedForeignId('group_id');
            }
        });
    }
};
