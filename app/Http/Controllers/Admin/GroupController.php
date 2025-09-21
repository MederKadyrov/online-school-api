<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{

    public function show(Group $group)
    {
        $group->load(['level','homeroomTeacher.user','students:id']);
        $ht = $group->homeroomTeacher;
        $u  = $ht?->user;
        $name = $u ? trim(implode(' ', array_filter([$u->last_name,$u->first_name,$u->middle_name]))) : null;

        return [
            'id'             => $group->id,
            'class_letter'   => $group->class_letter,
            'display_name'   => $group->display_name ?? null,
            'students_count' => $group->students()->count(),
            'level'          => $group->level ? [
                'id'     => $group->level->id,
                'number' => $group->level->number,
                'title'  => $group->level->title,
            ] : null,
            'homeroom_teacher_id' => $ht?->id,
            'homeroom' => $ht ? [
                'id'    => $ht->id,
                'user_id' => $u->id,
                'name'  => $name,
                'email' => $u->email,
                'phone' => $u->phone,
            ] : null,
        ];
    }

    /**
     * Список групп с уровнем и количеством студентов.
     * Фильтры:
     *  - level_id (новый, предпочтительный)
     *  - grade (legacy: число 1..12; конвертируем в level_id)
     */
    public function index(Request $r)
    {
        $q = Group::with(['level', 'homeroomTeacher.user'])
            ->withCount('students');

        if ($r->filled('level_id')) {
            $q->where('level_id', (int)$r->input('level_id'));
        } elseif ($r->filled('grade')) {
            $number = (int)$r->input('grade');
            $q->whereHas('level', fn($qq) => $qq->where('number', $number));
        }

        // сортируем по номеру уровня, не делая JOIN
        $q->orderBy(
            \App\Models\Level::select('number')->whereColumn('levels.id', 'groups.level_id')
        )->orderBy('class_letter');

        return $q->get()->map(function ($g) {
            $ht = $g->homeroomTeacher;
            $u  = $ht?->user;
            $name = $u ? trim(implode(' ', array_filter([$u->last_name,$u->first_name,$u->middle_name]))) : null;

            return [
                'id'             => $g->id,
                'class_letter'   => $g->class_letter,
                'students_count' => $g->students_count,
                'level'          => $g->level ? [
                    'id'     => $g->level->id,
                    'number' => $g->level->number,
                    'title'  => $g->level->title,
                ] : null,
                'display_name'   => $g->display_name ?? null,
                'homeroom'       => $ht ? [
                    'id'    => $ht->id,
                    'user_id' => $u->id,
                    'name'  => $name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                ] : null,
            ];
        });
    }

    /** Создание группы (только level_id + class_letter). */
    public function store(Request $r)
    {
        // legacy grade → level_id
        if (!$r->filled('level_id') && $r->filled('grade')) {
            $lvl = \App\Models\Level::where('number', (int)$r->input('grade'))->first();
            if ($lvl) $r->merge(['level_id' => $lvl->id]);
        }

        $data = $r->validate([
            'level_id'            => ['required','exists:levels,id'],
            'class_letter'        => ['nullable','string','max:2'],
            'homeroom_teacher_id' => ['nullable','exists:teachers,id'],
        ]);

        // Если хочешь запретить одному учителю быть классруком у нескольких групп:
        // if (!empty($data['homeroom_teacher_id'])) {
        //     $busy = \App\Models\Group::where('homeroom_teacher_id', $data['homeroom_teacher_id'])->exists();
        //     if ($busy) return response()->json(['message'=>'Этот преподаватель уже является классным руководителем другой группы'], 422);
        // }

        $group = Group::create($data);
        return response()->json($group->load(['level','homeroomTeacher.user']), 201);
    }

    /** Обновление группы. */
    public function update(Request $r, Group $group)
    {
        // Legacy: grade -> level_id
        if (!$r->filled('level_id') && $r->filled('grade')) {
            $lvl = Level::where('number', (int)$r->input('grade'))->first();
            if ($lvl) $r->merge(['level_id' => $lvl->id]);
        }

        $data = $r->validate([
            'level_id'     => ['sometimes','required','exists:levels,id'],
            'class_letter' => ['nullable','string','max:2'],
            'homeroom_teacher_id' => ['nullable','exists:teachers,id'],
        ]);

        // Уникальность классрука (если нужна):
        // $newTid = $data['homeroom_teacher_id'] ?? $group->homeroom_teacher_id;
        // if ($newTid) {
        //     $busy = \App\Models\Group::where('homeroom_teacher_id', $newTid)->where('id','!=',$group->id)->exists();
        //     if ($busy) return response()->json(['message'=>'Этот преподаватель уже является классным руководителем другой группы'], 422);
        // }

        $newLevelId   = $data['level_id']     ?? $group->level_id;
        $newClassLet  = $data['class_letter'] ?? $group->class_letter;

        $exists = Group::where('level_id', $newLevelId)
            ->where('class_letter', $newClassLet)
            ->where('id', '!=', $group->id)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'Такая группа уже существует (уровень + литера)'], 422);
        }

        $group->update($data);
        return response()->json($group->load('level','homeroomTeacher.user'));
    }

    /** Прикрепление студентов к группе (без изменений). */
    public function attachStudents(Request $r, Group $group)
    {
        $payload = $r->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $group->students()->syncWithoutDetaching($payload['student_ids']);

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Group $group)
    {
        // если у тебя FK students.group_id -> groups.id с ON DELETE SET NULL,
        // можно просто удалить. Если CASCADE — тоже ок.
        $group->delete();
        return response()->json(['message' => 'Group deleted']);
    }
}
