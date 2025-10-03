<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Assignment, AssignmentSubmission, Paragraph};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    private function authorizeParagraphOwner(Paragraph $paragraph, Request $r): void {
        $course = $paragraph->chapter->module->course;
        $this->authorize('manage', $course);
    }
    private function toFiveScale(int|float $score, int|float $max): int {
        if ($max <= 0) return 2;
        $pct = 100.0 * $score / $max;
        return $pct >= 90 ? 5 : ($pct >= 75 ? 4 : ($pct >= 60 ? 3 : 2));
    }

    public function store(Request $r, Paragraph $paragraph) {
        $this->authorizeParagraphOwner($paragraph, $r);
        $data = $r->validate([
            'title'           => 'required|string|max:150',
            'instructions'    => 'nullable|string',
            'due_at'          => 'nullable|date',
            'max_points'      => 'nullable|integer|min:1|max:1000',
            'attachments_path'=> 'nullable|string',   // ⬅ добавили
            'status'          => ['nullable', Rule::in(['draft','published'])],
        ]);
        $data['paragraph_id'] = $paragraph->id;
        $data['max_points']   = $data['max_points'] ?? 100;
        $data['status']       = $data['status'] ?? 'draft';
        return response()->json(Assignment::create($data), 201);
    }

    public function show(Request $r, Assignment $assignment) {
        $this->authorize('manage', $assignment->paragraph->chapter->module->course);
        return $assignment;
    }

    public function update(Request $r, Assignment $assignment) {
        $this->authorize('manage', $assignment->paragraph->chapter->module->course);
        $data = $r->validate([
            'title'           => 'sometimes|required|string|max:150',
            'instructions'    => 'sometimes|nullable|string',
            'due_at'          => 'sometimes|nullable|date',
            'max_points'      => 'sometimes|integer|min:1|max:1000',
            'attachments_path'=> 'sometimes|nullable|string',   // ⬅ добавили
            'status'          => ['sometimes', Rule::in(['draft','published'])],
        ]);
        $assignment->update($data);
        return $assignment->fresh();
    }

    public function publish(Request $r, Assignment $assignment) {
        $this->authorize('manage', $assignment->paragraph->chapter->module->course);
        $assignment->update(['status'=>'published']);
        return ['message'=>'ok'];
    }

    public function submissions(Request $r, Assignment $assignment) {
        $this->authorize('manage', $assignment->paragraph->chapter->module->course);
        $q = $assignment->submissions()->with(['student.user:id,last_name,first_name,middle_name,email,phone']);
        if ($r->filled('status')) $q->where('status', $r->string('status'));
        return $q->orderByDesc('id')->get()->map(function($s){
            $u = $s->student?->user;
            return [
                'id'       => $s->id,
                'student'  => $u ? [
                    'id'=>$s->student_id,
                    'name'=>trim(implode(' ', array_filter([$u->last_name,$u->first_name,$u->middle_name]))),
                    'email'=>$u->email
                ] : null,
                'submitted_at' => $s->submitted_at,
                'file_path'    => $s->file_path,
                'text_answer'  => $s->text_answer,
                'score'        => $s->score,
                'grade_5'      => $s->grade_5,
                'status'       => $s->status,
                'teacher_comment'=>$s->teacher_comment,
            ];
        });
    }

    public function grade(Request $r, AssignmentSubmission $submission) {
        $this->authorize('manage', $submission->assignment->paragraph->chapter->module->course);
        $data = $r->validate([
            'score'           => 'required|numeric|min:0',
            'teacher_comment' => 'nullable|string|max:2000',
            'status'          => ['nullable', Rule::in(['returned','needs_fix'])],
        ]);
        $max = $submission->assignment->max_points ?: 100;
        $grade5 = $this->toFiveScale($data['score'], $max);
        $submission->update([
            'score'          => $data['score'],
            'grade_5'        => $grade5,
            'teacher_comment'=> $data['teacher_comment'] ?? null,
            'status'         => $data['status'] ?? 'returned',
        ]);
        return ['message'=>'ok', 'grade_5'=>$grade5];
    }

    /** загрузка файла-условия к заданию (PDF/JPG/...) */
    public function uploadAttachment(Request $r) {
        $r->validate(['file'=>'required|file|max:102400']);
        $user = $r->user();
        if (!$user || !$user->hasRole('teacher')) abort(403);
        $path = $r->file('file')->store('assignments/'.date('Y/m/d'), 'public');
        return ['path'=>$path, 'url'=>Storage::disk('public')->url($path)];
    }

    public function byParagraph(Request $r, Paragraph $paragraph)
    {
        $this->authorizeParagraphOwner($paragraph, $r);
        $asg = $paragraph->assignments()->orderByDesc('id')->first(); // у нас unique, но на всякий случай
        return $asg ?: response()->json(null);
    }

    public function destroy(Request $r, Assignment $assignment)
    {
        $this->authorize('manage', $assignment->paragraph->chapter->module->course);

        // Если есть отправки — безопаснее не удалять «жёстко».
        $hasSubs = $assignment->submissions()->exists();

        if ($hasSubs) {
            // Вариант 1 (рекомендуется): переводим в «архив», скрываем от учеников
            $assignment->update(['status' => 'draft']); // или 'archived', если добавишь enum
            return response()->json([
                'message' => 'Задание скрыто от студентов (переведено в черновик), так как по нему есть отправки.'
            ], 200);
        }

        // Вариант 2: жёсткое удаление, если отправок нет
        $assignment->delete();
        return response()->json(['message' => 'Задание удалено'], 200);
    }
}

