<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Assignment, AssignmentSubmission, Paragraph, Grade};
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
            'attachments_path'=> 'nullable|string',
            'status'          => ['nullable', Rule::in(['draft','published'])],
        ]);
        $data['paragraph_id'] = $paragraph->id;
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
            'attachments_path'=> 'sometimes|nullable|string',
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

    /** Получить все отправки заданий учителя с фильтрами */
    public function allSubmissions(Request $r) {
        $teacher = $r->user()->teacher;
        abort_unless($teacher, 403);

        // Получаем все курсы учителя
        $courseIds = $teacher->courses()->pluck('courses.id');

        // Базовый запрос: отправки заданий из курсов учителя
        $query = AssignmentSubmission::query()
            ->with([
                'student.user:id,last_name,first_name,middle_name,email',
                'student.group:id,class_letter,level_id',
                'student.group.level:id,number',
                'assignment.paragraph.chapter.module.course:id,title'
            ])
            ->whereHas('assignment.paragraph.chapter.module.course', function($q) use ($courseIds) {
                $q->whereIn('courses.id', $courseIds);
            });

        // Фильтр по статусу (submitted, returned)
        if ($r->filled('status')) {
            $query->where('status', $r->string('status'));
        }

        // Фильтр по курсу
        if ($r->filled('course_id')) {
            $query->whereHas('assignment.paragraph.chapter.module.course', function($q) use ($r) {
                $q->where('courses.id', $r->integer('course_id'));
            });
        }

        // Фильтр по группе
        if ($r->filled('group_id')) {
            $query->whereHas('student', function($q) use ($r) {
                $q->where('group_id', $r->integer('group_id'));
            });
        }

        // Фильтр по конкретному заданию
        if ($r->filled('assignment_id')) {
            $query->where('assignment_id', $r->integer('assignment_id'));
        }

        // Поиск по студенту (имя)
        if ($r->filled('student_search')) {
            $search = $r->string('student_search')->trim();
            $query->whereHas('student.user', function($q) use ($search) {
                $q->where(function($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('middle_name', 'like', "%{$search}%");
                });
            });
        }

        $submissions = $query->orderByDesc('submitted_at')->orderByDesc('id')->get();

        return $submissions->map(function($s) {
            $u = $s->student?->user;
            $group = $s->student?->group;
            $course = $s->assignment?->paragraph?->chapter?->module?->course;

            return [
                'id'              => $s->id,
                'student'         => $u ? [
                    'id'   => $s->student_id,
                    'name' => trim(implode(' ', array_filter([$u->last_name, $u->first_name, $u->middle_name]))),
                    'email'=> $u->email
                ] : null,
                'group'           => $group ? [
                    'id'   => $group->id,
                    'name' => $group->display_name
                ] : null,
                'course'          => $course ? [
                    'id'    => $course->id,
                    'title' => $course->title
                ] : null,
                'assignment'      => [
                    'id'    => $s->assignment_id,
                    'title' => $s->assignment?->title
                ],
                'submitted_at'    => $s->submitted_at,
                'file_path'       => $s->file_path,
                'text_answer'     => $s->text_answer,
                'score'           => $s->score,
                'grade_5'         => $s->grade_5,
                'status'          => $s->status,
                'teacher_comment' => $s->teacher_comment,
            ];
        });
    }

    public function grade(Request $r, AssignmentSubmission $submission) {
        $this->authorize('manage', $submission->assignment->paragraph->chapter->module->course);
        $data = $r->validate([
            'grade_5'         => 'required|integer|min:2|max:5',
            'teacher_comment' => 'nullable|string|max:2000',
            'status'          => ['nullable', Rule::in(['returned','needs_fix'])],
        ]);

        $submission->update([
            'score'          => null,
            'grade_5'        => $data['grade_5'],
            'teacher_comment'=> $data['teacher_comment'] ?? null,
            'status'         => $data['status'] ?? 'returned',
        ]);

        // Создаем/обновляем запись в таблице grades
        $teacher = $r->user()->teacher;
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
                    'teacher_id' => $teacher?->id,
                    'score' => null,
                    'grade_5' => $data['grade_5'],
                    'max_points' => null,
                    'title' => $submission->assignment->title,
                    'teacher_comment' => $data['teacher_comment'] ?? null,
                    'graded_at' => now(),
                ]
            );
        }

        return ['message'=>'ok', 'grade_5'=>$data['grade_5']];
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
        $asg = $paragraph->assignment()->orderByDesc('id')->first(); // у нас unique, но на всякий случай
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

    /** Получить все задания конкретного курса */
    public function assignmentsByCourse(Request $r, \App\Models\Course $course)
    {
        $this->authorize('manage', $course);

        // Получаем все задания курса с информацией о параграфе, главе и модуле
        $assignments = Assignment::query()
            ->whereHas('paragraph.chapter.module', function($q) use ($course) {
                $q->where('course_id', $course->id);
            })
            ->with(['paragraph.chapter.module'])
            ->where('status', 'published')
            ->orderBy('id', 'desc')
            ->get();

        return $assignments->map(function($a) {
            $paragraph = $a->paragraph;
            $chapter = $paragraph?->chapter;
            $module = $chapter?->module;

            // Формат: "Модуль 1 → Глава 2 → Параграф 3 → Задание"
            $path = collect([
                $module ? "М{$module->number}" : null,
                $chapter ? "Гл{$chapter->number}" : null,
                $paragraph ? "§{$paragraph->position}" : null,
            ])->filter()->implode(' → ');

            $displayName = $path ? "{$path} → {$a->title}" : $a->title;

            // Ограничим длину до 80 символов
            if (mb_strlen($displayName) > 80) {
                $displayName = mb_substr($displayName, 0, 77) . '...';
            }

            return [
                'id' => $a->id,
                'title' => $a->title,
                'display_name' => $displayName,
            ];
        });
    }
}

