<?php

namespace App\Observers;

use App\Models\QuizAttempt;
use App\Models\Grade;

class QuizAttemptObserver
{
    /**
     * Handle the QuizAttempt "updated" event.
     * Создаём/обновляем оценку в таблице grades при завершении теста
     */
    public function updated(QuizAttempt $attempt): void
    {
        // Создаём оценку только если тест завершён
        if ($attempt->status === 'finished' && $attempt->isDirty('status')) {
            $this->createOrUpdateGrade($attempt);
        }
    }

    private function createOrUpdateGrade(QuizAttempt $attempt): void
    {
        $quiz = $attempt->quiz;

        // Получаем course_id через quiz -> paragraph -> chapter -> module -> course
        $courseId = $quiz->paragraph?->chapter?->module?->course_id;

        if (!$courseId) {
            \Log::warning("Cannot create grade: course_id not found for quiz_attempt {$attempt->id}");
            return;
        }

        // Создаём или обновляем запись в grades
        $grade = Grade::updateOrCreate(
            [
                'gradeable_type' => QuizAttempt::class,
                'gradeable_id' => $attempt->id,
            ],
            [
                'student_id' => $attempt->student_id,
                'course_id' => $courseId,
                'teacher_id' => $quiz->paragraph->chapter->module->course->teacher_id,
                'score' => $attempt->score,
                'grade_5' => $attempt->grade_5,
                'max_points' => $quiz->max_points,
                'title' => $quiz->title,
                'graded_at' => $attempt->finished_at,
            ]
        );

        // Обновляем grade_id в попытке теста
        $attempt->updateQuietly(['grade_id' => $grade->id]);
    }
}
