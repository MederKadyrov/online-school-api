<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Assignment, AssignmentSubmission, Paragraph, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentSubmissionController extends Controller
{
    private function meStudentId(Request $r): int {
        $student = $r->user()->student;
        abort_unless($student, 403, 'Not a student');
        return $student->id;
    }

    public function listForParagraph(Request $r, Paragraph $paragraph) {
        // Показываем только опубликованные
        $assignments = $paragraph->assignment()->where('status','published')
            ->orderBy('id','desc')->get(['id','title','instructions','due_at','max_points','attachments_path']);
        return $assignments;
    }

    public function allAssignments(Request $r) {
        $sid = $this->meStudentId($r);
        $student = $r->user()->student;

        // Get student's group ID
        $groupId = $student->group_id;

        if (!$groupId) {
            return response()->json([]);
        }

        // Get all published assignments from courses assigned to student's group
        $query = Assignment::with(['paragraph.chapter.module.course', 'submissions' => function($q) use ($sid) {
            $q->where('student_id', $sid);
        }])
        ->where('status', 'published')
        ->whereHas('paragraph.chapter.module.course', function($q) use ($groupId) {
            $q->whereHas('groups', function($q2) use ($groupId) {
                $q2->where('groups.id', $groupId);
            });
        });

        // Filter by course if provided
        if ($r->filled('course_id')) {
            $query->whereHas('paragraph.chapter.module', function($q) use ($r) {
                $q->where('course_id', $r->input('course_id'));
            });
        }

        // Filter by status if provided
        if ($r->filled('status')) {
            $status = $r->input('status');
            if ($status === 'pending') {
                $query->whereDoesntHave('submissions', function($q) use ($sid) {
                    $q->where('student_id', $sid);
                });
            } elseif ($status === 'submitted') {
                $query->whereHas('submissions', function($q) use ($sid) {
                    $q->where('student_id', $sid)->where('status', 'submitted');
                });
            } elseif ($status === 'graded') {
                $query->whereHas('submissions', function($q) use ($sid) {
                    $q->where('student_id', $sid)->where('status', 'graded');
                });
            }
        }

        $assignments = $query->orderBy('due_at', 'asc')->get();

        // Format response
        $result = $assignments->map(function($assignment) {
            $submission = $assignment->submissions->first();

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->instructions,
                'deadline' => $assignment->due_at,
                'max_points' => $assignment->max_points,
                'paragraph_id' => $assignment->paragraph_id,
                'course_title' => $assignment->paragraph->chapter->module->course->title ?? '',
                'module_title' => $assignment->paragraph->chapter->module->title ?? '',
                'chapter_title' => $assignment->paragraph->chapter->title ?? '',
                'submission_status' => $submission ? $submission->status : null,
                'grade' => $submission && $submission->grade ? $submission->grade->grade_5 : null,
            ];
        });

        return response()->json($result);
    }

    public function mySubmission(Request $r, Assignment $assignment) {
        $sid = $this->meStudentId($r);
        return AssignmentSubmission::where('assignment_id',$assignment->id)
            ->where('student_id', $sid)->first();
    }

    /** Принимает multipart/form-data (file) + text_answer, или JSON без файла */
    public function submit(Request $r, Assignment $assignment) {
        $sid = $this->meStudentId($r);
        if ($assignment->status !== 'published') abort(403, 'Assignment not published');

        $isMultipart = $r->hasFile('file');
        if ($isMultipart) {
            $r->validate(['file'=>'file|max:102400', 'text_answer'=>'nullable|string']);
            $path = $r->file('file')?->store('submissions/'.date('Y/m/d'), 'public');
            $payload = [
                'text_answer' => $r->input('text_answer'),
                'file_path'   => $path,
            ];
        } else {
            $data = $r->validate([
                'text_answer'=>'nullable|string',
                'file_path'  =>'nullable|string' // если фронт сначала грузит файл отдельным вызовом
            ]);
            $payload = $data;
        }

        $sub = AssignmentSubmission::updateOrCreate(
            ['assignment_id'=>$assignment->id, 'student_id'=>$sid],
            $payload + ['submitted_at'=>now(), 'status'=>'submitted']
        );

        // Update student progress after assignment submission
        $paragraphId = $assignment->paragraph_id;
        if ($paragraphId) {
            $progressController = app(\App\Http\Controllers\Student\ProgressController::class);
            $progressController->updateProgress($r, Paragraph::find($paragraphId));
        }

        return response()->json($sub, 201);
    }
}

