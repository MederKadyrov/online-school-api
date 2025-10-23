<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Paragraph;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\StudentProgress;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /**
     * Get student's progress for a specific course
     */
    public function getCourseProgress(Request $request, $courseId)
    {
        abort_unless($request->user()->hasRole('student'), 403);

        $student = $request->user()->student;

        // Get all paragraphs in the course
        $paragraphs = Paragraph::whereHas('chapter.module', function($q) use ($courseId) {
            $q->where('course_id', $courseId);
        })->with(['chapter.module', 'assignment', 'quiz'])->get();

        // Get student progress for these paragraphs
        $progress = StudentProgress::where('student_id', $student->id)
            ->whereIn('paragraph_id', $paragraphs->pluck('id'))
            ->get()
            ->keyBy('paragraph_id');

        // Build response with progress for each paragraph
        $paragraphsWithProgress = $paragraphs->map(function($paragraph) use ($progress, $student) {
            $progressRecord = $progress->get($paragraph->id);

            return [
                'paragraph_id' => $paragraph->id,
                'status' => $progressRecord ? $progressRecord->status : 'not_started',
                'last_visited_at' => $progressRecord ? $progressRecord->last_visited_at : null,
                'completed_at' => $progressRecord ? $progressRecord->completed_at : null,
            ];
        });

        return response()->json($paragraphsWithProgress);
    }

    /**
     * Update progress when student visits a paragraph
     */
    public function updateProgress(Request $request, Paragraph $paragraph)
    {
        abort_unless($request->user()->hasRole('student'), 403);

        $student = $request->user()->student;

        // Check if paragraph has any assignments or quizzes
        $hasAssignment = $paragraph->assignment()->exists();
        $hasQuiz = $paragraph->quiz()->exists();

        // Determine if paragraph is completed
        $completed = $this->isParagraphCompleted($student->id, $paragraph);

        $status = 'in_progress';
        $completedAt = null;

        if ($completed) {
            $status = 'completed';
            $completedAt = now();
        }

        // Update or create progress record
        $progress = StudentProgress::updateOrCreate(
            [
                'student_id' => $student->id,
                'paragraph_id' => $paragraph->id,
            ],
            [
                'status' => $status,
                'last_visited_at' => now(),
                'completed_at' => $completedAt,
            ]
        );

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    /**
     * Check if a paragraph is completed by a student
     */
    private function isParagraphCompleted($studentId, Paragraph $paragraph): bool
    {
        $hasAssignment = $paragraph->assignment()->exists();
        $hasQuiz = $paragraph->quiz()->exists();

        // If no assignment and no quiz, paragraph is completed when visited
        if (!$hasAssignment && !$hasQuiz) {
            return true;
        }

        $assignmentCompleted = true;
        $quizCompleted = true;

        // Check assignment completion
        if ($hasAssignment) {
            $assignment = $paragraph->assignment;
            $submission = AssignmentSubmission::where('student_id', $studentId)
                ->where('assignment_id', $assignment->id)
                ->where('status', '!=', 'draft')
                ->exists();

            $assignmentCompleted = $submission;
        }

        // Check quiz completion
        if ($hasQuiz) {
            $quiz = $paragraph->quiz;
            $attempt = QuizAttempt::where('student_id', $studentId)
                ->where('quiz_id', $quiz->id)
                ->whereNotNull('finished_at')
                ->exists();

            $quizCompleted = $attempt;
        }

        return $assignmentCompleted && $quizCompleted;
    }
}
