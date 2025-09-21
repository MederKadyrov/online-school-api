<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Level;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupStudentsController extends Controller
{
    /** Студенты внутри группы */
    public function listInGroup(Group $group, Request $r)
    {
        $q = Student::with(['group:id,level_id,class_letter','level:id,number'])
            ->with(['user:id,last_name,first_name,middle_name,email,phone'])
            ->where('group_id', $group->id);

        if ($r->filled('search')) {
            $term = '%'.$r->string('search')->toString().'%';
            $q->whereHas('user', function ($uq) use ($term) {
                $uq->where('last_name','like',$term)
                    ->orWhere('first_name','like',$term)
                    ->orWhere('middle_name','like',$term)
                    ->orWhere('email','like',$term)
                    ->orWhere('phone','like',$term);
            });
        }

        return $q->orderBy('id','desc')->limit(500)->get()->map(fn($s) => [
            'id'    => $s->id,
            'user'  => [
                'id'    => $s->user->id,
                'name'  => trim(implode(' ', array_filter([$s->user->last_name,$s->user->first_name,$s->user->middle_name]))),
                'email' => $s->user->email,
                'phone' => $s->user->phone,
            ],
            'level' => $s->level?->number,
            'group' => $s->group ? [
                'id' => $s->group->id,
                'display' => $s->group->display_name ?? ($s->group->level?->number . ($s->group->class_letter ? '-'.$s->group->class_letter : '')),
            ] : null,
        ]);
    }

    /** Список для подбора: можно отфильтровать по уровню, без группы и по поиску */
    public function listForPickup(Request $r)
    {
        $q = Student::with(['level:id,number', 'user:id,last_name,first_name,middle_name,email,phone']);

        // только без группы?
        if ($r->boolean('unassigned', false)) {
            $q->whereNull('group_id');
        }

        // фильтр по уровню
        if ($r->filled('level_id')) {
            $q->where('level_id', (int)$r->input('level_id'));
        } elseif ($r->filled('grade')) {
            $level = Level::where('number', (int)$r->input('grade'))->first();
            if ($level) $q->where('level_id', $level->id);
        }

        if ($r->filled('search')) {
            $term = '%'.$r->string('search')->toString().'%';
            $q->whereHas('user', function ($uq) use ($term) {
                $uq->where('last_name','like',$term)
                    ->orWhere('first_name','like',$term)
                    ->orWhere('middle_name','like',$term)
                    ->orWhere('email','like',$term)
                    ->orWhere('phone','like',$term);
            });
        }

        return $q->orderBy('id','desc')->limit(500)->get()->map(fn($s) => [
            'id'    => $s->id,
            'user'  => [
                'id'    => $s->user->id,
                'name'  => trim(implode(' ', array_filter([$s->user->last_name,$s->user->first_name,$s->user->middle_name]))),
                'email' => $s->user->email,
                'phone' => $s->user->phone,
            ],
            'level' => $s->level?->number,
            'group_id' => $s->group_id,
        ]);
    }

    /** Массовое назначение в группу, с проверкой уровня */
    public function attach(Group $group, Request $r)
    {
        $payload = $r->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        // Проверка: студенты должны быть того же уровня, что и группа
        $bad = Student::whereIn('id', $payload['student_ids'])
            ->where('level_id', '!=', $group->level_id)
            ->pluck('id')->all();

        if ($bad) {
            return response()->json([
                'message' => 'Некоторые студенты не соответствуют уровню группы',
                'mismatched_student_ids' => $bad,
            ], 422);
        }

        DB::transaction(function () use ($group, $payload) {
            Student::whereIn('id', $payload['student_ids'])->update(['group_id' => $group->id]);
        });

        return response()->json(['message' => 'ok']);
    }

    /** Массовое снятие с группы */
    public function detach(Group $group, Request $r)
    {
        $payload = $r->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        DB::transaction(function () use ($group, $payload) {
            Student::whereIn('id', $payload['student_ids'])
                ->where('group_id', $group->id)
                ->update(['group_id' => null]);
        });

        return response()->json(['message' => 'ok']);
    }

    /** Перевод студентов из группы в другую (с проверкой уровня) */
    public function move(Group $group, Request $r)
    {
        $payload = $r->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'to_group_id'   => 'required|exists:groups,id',
        ]);

        $to = Group::findOrFail($payload['to_group_id']);

        if ($to->level_id !== $group->level_id) {
            return response()->json(['message' => 'Нельзя переводить между разными уровнями'], 422);
        }

        DB::transaction(function () use ($group, $to, $payload) {
            Student::whereIn('id', $payload['student_ids'])
                ->where('group_id', $group->id)
                ->update(['group_id' => $to->id]);
        });

        return response()->json(['message' => 'ok']);
    }
}
