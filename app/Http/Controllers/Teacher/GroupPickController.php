<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupPickController extends Controller
{
    public function index(Request $r)
    {
        $q = Group::with(['level:id,number','homeroomTeacher.user:id,last_name,first_name,middle_name,email'])
            ->withCount('students')
            ->orderBy('class_letter');

        if ($r->filled('level_id')) {
            $q->where('level_id', (int)$r->input('level_id'));
        }
        if ($r->filled('search')) {
            $term = '%'.$r->string('search')->toString().'%';
            // Поиск по «отображаемому» названию/литере/номеру
            $q->where(function($qq) use ($term){
                $qq->where('class_letter','like',$term)
                    ->orWhereHas('level', fn($l)=>$l->where('number','like',$term));
            });
        }

        return $q->limit(500)->get()->map(function ($g) {
            $htUser = $g->homeroomTeacher?->user;
            $htName = $htUser ? trim(implode(' ', array_filter([$htUser->last_name,$htUser->first_name,$htUser->middle_name]))) : null;
            return [
                'id'             => $g->id,
                'level'          => $g->level?->number,
                'class_letter'   => $g->class_letter,
                'display_name'   => $g->display_name ?? ($g->level?->number.($g->class_letter ? '-'.$g->class_letter : '')),
                'students_count' => $g->students_count,
                'homeroom'       => $htUser ? ['name'=>$htName, 'email'=>$htUser->email] : null,
            ];
        });
    }
}
