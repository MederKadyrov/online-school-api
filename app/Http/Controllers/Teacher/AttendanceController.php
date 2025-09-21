<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBulkRequest;
use App\Models\Attendance;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function store(AttendanceBulkRequest $r)
    {
        $lesson = Lesson::findOrFail($r->lesson_id);

        // учитель может отмечать только свои уроки
        if (auth()->user()->hasRole('teacher')) {
            abort_unless(
                $lesson->teacher_id === (auth()->user()->teacher->id ?? 0),
                403, 'Можно отмечать только свои уроки'
            );
        }

        DB::transaction(function () use ($lesson, $r) {
            foreach ($r->items as $row) {
                Attendance::updateOrCreate(
                    ['lesson_id' => $lesson->id, 'student_id' => $row['student_id']],
                    ['status' => $row['status'], 'comment' => $row['comment'] ?? null]
                );
            }
        });

        return response()->json(['message'=>'ok'], 201);
    }

    public function index(Request $r, Lesson $lesson)
    {
        $this->authorize('view', $lesson);
        $rows = Attendance::with('student.user')
            ->where('lesson_id', $lesson->id)
            ->get();
        return $rows;
    }

    public function students(Lesson $lesson)
    {
        $this->authorize('view', $lesson);

        $students = $lesson->group
            ->students()
            ->with('user:id,name,sex,phone,email')
            ->get(['students.id','students.user_id','students.grade','students.class_letter']);

        return $students->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->user->name,
            'email' => $s->user->email,
            'phone' => $s->user->phone,
            'sex' => $s->user->sex,
            'level_id' => $s->level_id,
            'class_letter' => $s->class_letter,
        ]);
    }
}

