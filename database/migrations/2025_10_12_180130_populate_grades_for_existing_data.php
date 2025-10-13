<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\{QuizAttempt, AssignmentSubmission, Grade};

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Заполняем grades для существующих QuizAttempt
        $quizAttempts = QuizAttempt::with('quiz.paragraph.chapter.module')
            ->whereIn('status', ['graded', 'submitted'])
            ->whereNotNull('grade_5')
            ->get();

        foreach ($quizAttempts as $attempt) {
            $courseId = $attempt->quiz->paragraph->chapter->module->course_id ?? null;
            if ($courseId) {
                Grade::updateOrCreate(
                    [
                        'student_id' => $attempt->student_id,
                        'gradeable_type' => QuizAttempt::class,
                        'gradeable_id' => $attempt->id,
                    ],
                    [
                        'course_id' => $courseId,
                        'teacher_id' => null,
                        'score' => $attempt->score ?? 0,
                        'grade_5' => $attempt->grade_5,
                        'max_points' => $attempt->quiz->max_points ?? 0,
                        'title' => $attempt->quiz->title,
                        'graded_at' => $attempt->finished_at ?? now(),
                    ]
                );
            }
        }

        // Заполняем grades для существующих AssignmentSubmission
        $submissions = AssignmentSubmission::with('assignment.paragraph.chapter.module')
            ->whereIn('status', ['returned', 'needs_fix'])
            ->whereNotNull('grade_5')
            ->get();

        foreach ($submissions as $submission) {
            $courseId = $submission->assignment->paragraph->chapter->module->course_id ?? null;
            if ($courseId) {
                Grade::updateOrCreate(
                    [
                        'student_id' => $submission->student_id,
                        'gradeable_type' => AssignmentSubmission::class,
                        'gradeable_id' => $submission->id,
                    ],
                    [
                        'course_id' => $courseId,
                        'score' => $submission->score ?? 0,
                        'grade_5' => $submission->grade_5,
                        'max_points' => $submission->assignment->max_points ?? 100,
                        'title' => $submission->assignment->title,
                        'teacher_comment' => $submission->teacher_comment,
                        'graded_at' => $submission->updated_at ?? now(),
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем созданные записи (опционально)
        Grade::whereIn('gradeable_type', [QuizAttempt::class, AssignmentSubmission::class])->delete();
    }
};
