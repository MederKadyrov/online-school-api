<?php

namespace App\Observers;

use App\Models\AssignmentSubmission;
use App\Models\Grade;

class AssignmentSubmissionObserver
{
    /**
     * Handle the AssignmentSubmission "updated" event.
     * Создаём/обновляем оценку в таблице grades при проверке задания
     */
    public function updated(AssignmentSubmission $submission): void
    {
        // Создаём оценку только если работа проверена (статус returned)
        if ($submission->status === 'returned' && $submission->grade_5 !== null) {
            $this->createOrUpdateGrade($submission);
        }
    }

    private function createOrUpdateGrade(AssignmentSubmission $submission): void
    {
        $assignment = $submission->assignment;

        // Получаем course_id через assignment -> paragraph -> chapter -> module -> course
        $courseId = $assignment->paragraph?->chapter?->module?->course_id;

        if (!$courseId) {
            \Log::warning("Cannot create grade: course_id not found for assignment_submission {$submission->id}");
            return;
        }

        // Создаём или обновляем запись в grades
        $grade = Grade::updateOrCreate(
            [
                'gradeable_type' => AssignmentSubmission::class,
                'gradeable_id' => $submission->id,
            ],
            [
                'student_id' => $submission->student_id,
                'course_id' => $courseId,
                'teacher_id' => $assignment->paragraph->chapter->module->course->teacher_id,
                'score' => $submission->score,
                'grade_5' => $submission->grade_5,
                'max_points' => $assignment->max_points,
                'title' => $assignment->title,
                'teacher_comment' => $submission->teacher_comment,
                'graded_at' => now(),
            ]
        );

        // Обновляем grade_id в работе студента
        $submission->updateQuietly(['grade_id' => $grade->id]);
    }
}
