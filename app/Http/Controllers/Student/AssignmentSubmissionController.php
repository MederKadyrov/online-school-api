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
        return response()->json($sub, 201);
    }
}

