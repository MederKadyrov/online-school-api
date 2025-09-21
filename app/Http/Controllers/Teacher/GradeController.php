<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    // список оценок по уроку
    public function index(Lesson $lesson)
    {
        $this->authorize('view', $lesson);
        $rows = Grade::where('lesson_id', $lesson->id)->get(['student_id','value','comment']);
        return $rows;
    }

    // массовое выставление/обновление оценок по уроку
    public function store(Request $r)
    {
        $data = $r->validate([
            'lesson_id' => ['required','exists:lessons,id'],
            'items'     => ['required','array','min:1'],
            'items.*.student_id' => ['required','exists:students,id'],
            'items.*.value'      => ['required','integer','between:1,10'], // при желании сменить границы
            'items.*.comment'    => ['nullable','string','max:255'],
        ]);

        $lesson = Lesson::with('teacher')->findOrFail($data['lesson_id']);
        // учитель может ставить оценки только по своим урокам
        if (auth()->user()->hasRole('teacher')) {
            abort_unless(
                $lesson->teacher_id === (auth()->user()->teacher->id ?? 0),
                403, 'Можно оценивать только свои уроки'
            );
        } else {
            // admin/staff — ок
            abort_unless(auth()->user()->hasAnyRole(['admin','staff']), 403);
        }

        // подтянем посещаемость разом
        $statuses = Attendance::where('lesson_id', $lesson->id)
            ->whereIn('student_id', collect($data['items'])->pluck('student_id'))
            ->pluck('status','student_id');

        // запрещённые статусы для оценки
        $blocked = ['excused_absence','unexcused_absence'];

        DB::transaction(function () use ($data, $lesson, $statuses, $blocked) {
            foreach ($data['items'] as $row) {
                $sid = (int)$row['student_id'];
                $st  = $statuses[$sid] ?? null;

                // если нет записи посещаемости — тоже блокируем (чтобы не было путаницы)
                if (!$st || in_array($st, $blocked, true)) {
                    abort(422, "Студент {$sid} отсутствовал или нет отметки посещаемости — оценка запрещена");
                }

                Grade::updateOrCreate(
                    ['lesson_id' => $lesson->id, 'student_id' => $sid],
                    [
                        'teacher_id' => $lesson->teacher_id,
                        'value'      => (int)$row['value'],
                        'comment'    => $row['comment'] ?? null,
                        'graded_at'  => now(),
                    ]
                );
            }
        });

        return response()->json(['message'=>'ok'], 201);
    }
}
