<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $r)
    {
        $q = Student::with(['user:id,last_name,first_name,middle_name,email,phone','level'])
            ->orderByDesc('id');

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

        if ($r->filled('level_id')) {
            $q->where('level_id', $r->integer('level_id'));
        }
        // опциональная совместимость:
        if ($r->filled('grade')) {
            $q->whereHas('level', fn($qq) => $qq->where('number', (int)$r->input('grade')));
        }

        return $q->limit(100)->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->user->name, // аксессор склеит ФИО
            'email' => $s->user->email,
            'phone' => $s->user->phone,
            'level' => $s->level ? [
                'id' => $s->level->id,
                'number' => $s->level->number,
                'title' => $s->level->title,
            ] : null,
        ]);
    }
}
