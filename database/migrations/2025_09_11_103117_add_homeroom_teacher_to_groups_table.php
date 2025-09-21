<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('groups', function (Blueprint $t) {
            if (!Schema::hasColumn('groups','homeroom_teacher_id')) {
                $t->foreignId('homeroom_teacher_id')
                    ->nullable()
                    ->after('level_id')
                    ->constrained('teachers')        // references teachers.id
                    ->nullOnDelete();                // если учителя удалят — поле обнулится
            }
        });

        // Если хочешь запрещать одному учителю быть класcруком у нескольких групп, добавь уникальный индекс:
        // Schema::table('groups', function (Blueprint $t) {
        //     $t->unique('homeroom_teacher_id', 'groups_homeroom_teacher_unique');
        // });
    }

    public function down(): void {
        Schema::table('groups', function (Blueprint $t) {
            if (Schema::hasColumn('groups','homeroom_teacher_id')) {
                $t->dropConstrainedForeignId('homeroom_teacher_id');
                // $t->dropUnique('groups_homeroom_teacher_unique'); // если включал выше
            }
        });
    }
};
