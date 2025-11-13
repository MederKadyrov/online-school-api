<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Переносим данные из module_grades в grades с полиморфной связью
     */
    public function up(): void
    {
        // Переносим все записи из module_grades в grades
        $moduleGrades = DB::table('module_grades')->get();

        foreach ($moduleGrades as $mg) {
            DB::table('grades')->insert([
                'student_id' => $mg->student_id,
                'course_id' => $mg->course_id,
                'teacher_id' => $mg->teacher_id,
                'gradeable_type' => 'App\\Models\\Module',
                'gradeable_id' => $mg->module_id,
                'score' => null, // У модульных оценок нет score
                'grade_5' => $mg->grade_5,
                'max_points' => null,
                'title' => 'Модульная оценка', // Можно получить название модуля, но пока так
                'teacher_comment' => $mg->teacher_comment,
                'graded_at' => $mg->graded_at,
                'created_at' => $mg->created_at,
                'updated_at' => $mg->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем все модульные оценки из grades
        DB::table('grades')
            ->where('gradeable_type', 'App\\Models\\Module')
            ->delete();
    }
};
