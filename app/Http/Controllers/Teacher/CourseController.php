<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Course, Module, Subject, Level, Group};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    /** Список курсов текущего преподавателя (фильтры: subject_id, level_id) */
    public function index(Request $r)
    {
        $teacherId = $r->user()->teacher->id;

        $q = Course::with(['subject:id,name','level:id,number'])
            ->where('teacher_id', $teacherId);

        if ($r->filled('subject_id')) $q->where('subject_id', (int)$r->input('subject_id'));
        if ($r->filled('level_id'))   $q->where('level_id', (int)$r->input('level_id'));

        return $q->orderByDesc('id')->get()->map(fn($c)=>[
            'id'      => $c->id,
            'title'   => $c->title,
            'slug'    => $c->slug,
            'status'  => $c->status,
            'level'   => $c->level?->number,
            'subject' => $c->subject?->name,
        ]);
    }

    /** Создание курса + автосоздание 4 модулей */
    public function store(Request $r)
    {
        $this->authorize('create', Course::class);

        $data = $r->validate([
            'subject_id' => 'required|exists:subjects,id',
            'level_id'   => 'required|exists:levels,id',
            'title'      => 'nullable|string|max:150',
        ]);

        $teacherId = $r->user()->teacher->id;

        $allowed = $r->user()->teacher->subjects()->where('subjects.id', $data['subject_id'])->exists();
        abort_unless($allowed, 403, 'Subject not assigned to teacher');


        return DB::transaction(function () use ($data, $teacherId) {
            // title по умолчанию: "<Предмет>, <N> класс"
            if (empty($data['title'])) {
                $subjectName = Subject::find($data['subject_id'])->name ?? 'Курс';
                $levelNum    = Level::find($data['level_id'])->number ?? '';
                $data['title'] = trim($subjectName . ($levelNum ? ", {$levelNum} класс" : ''));
            }

            $slug = Str::slug($data['title']);
            // уникализируем slug
            $base = $slug; $i = 1;
            while (Course::where('slug',$slug)->exists()) { $slug = $base.'-'.$i++; }

            $course = Course::create([
                'subject_id' => $data['subject_id'],
                'teacher_id' => $teacherId,
                'level_id'   => $data['level_id'],
                'title'      => $data['title'],
                'slug'       => $slug,
                'status'     => 'draft',
            ]);

            // 4 модуля
            for ($n=1; $n<=4; $n++) {
                Module::create([
                    'course_id' => $course->id,
                    'number'    => $n,
                    'position'  => $n,
                    'title'     => "Модуль {$n}",
                ]);
            }

            return response()->json($course->load(['subject:id,name','level:id,number','modules:id,course_id,number,title,position']), 201);
        });
    }

    /** Карточка курса (со списком модулей) */
    public function show(Course $course)
    {
        return $course->load([
            'subject:id,name',
            'level:id,number',
            'modules' => function($q){ $q->orderBy('position'); },
            'groups:id,level_id,class_letter',
            'groups.level:id,number',
        ]);
    }

    public function update(Request $r, Course $course)
    {
        $payload = $r->validate([
            'title'  => 'sometimes|required|string|max:150',
            'status' => 'sometimes|required|in:draft,published,archived',
        ]);

        if (isset($payload['status']) && $payload['status']==='published' && !$course->published_at) {
            $payload['published_at'] = now();
        }
        if (isset($payload['status']) && $payload['status']!=='published') {
            $payload['published_at'] = null;
        }

        $course->update($payload);
        return $course->fresh()->load('subject:id,name','level:id,number');
    }

    public function destroy(Course $course)
    {
        $course->delete();
        return response()->json(['message'=>'deleted']);
    }

    /** Привязка курса к группам (sync) */
    public function syncGroups(Request $r, Course $course)
    {
        $data = $r->validate([
            'group_ids'   => 'required|array|min:1',
            'group_ids.*' => 'integer|exists:groups,id',
        ]);

        // Проверка: все группы того же уровня, что и курс
        $mismatch = Group::whereIn('id',$data['group_ids'])
            ->where('level_id','!=',$course->level_id)
            ->pluck('id')->all();

        if ($mismatch) {
            return response()->json([
                'message' => 'Некоторые группы другого уровня',
                'mismatch_group_ids' => $mismatch,
            ], 422);
        }

        $course->groups()->sync($data['group_ids']);
        return response()->json(['message'=>'ok']);
    }

    public function mySubjects(Request $r) 
    { 
        $teacher = $r->user()->teacher; 
        return $teacher?->subjects()
            ->select('subjects.id','subjects.name','subjects.code')
            ->orderBy('subjects.name')
            ->get();
    }
}

