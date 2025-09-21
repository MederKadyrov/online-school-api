<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $r)
    {
        $q = Subject::with('area:id,name,code')->orderBy('name');

        if ($r->filled('area_id')) {
            $q->where('area_id', (int)$r->input('area_id'));
        }
        if ($r->filled('search')) {
            $term = '%'.$r->string('search')->toString().'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name','like',$term)
                    ->orWhere('code','like',$term)
                    ->orWhereHas('area', fn($aq) => $aq->where('name','like',$term)->orWhere('code','like',$term));
            });
        }

        return $q->get()->map(fn($s)=>[
            'id'      => $s->id,
            'name'    => $s->name,
            'code'    => $s->code,
            'area_id' => $s->area_id,
            'area'    => $s->area ? ['id'=>$s->area->id, 'name'=>$s->area->name, 'code'=>$s->area->code] : null,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'    => 'required|string|max:100',
            'code'    => 'required|string|max:50|unique:subjects,code',
            'area_id' => 'nullable|exists:educational_areas,id',
        ]);

        $subject = Subject::create($data);

        return response()->json(
            $subject->load('area:id,name,code'),
            201
        );
    }

    public function show(Subject $subject)
    {
        $subject->load('area:id,name,code');
        return [
            'id'      => $subject->id,
            'name'    => $subject->name,
            'code'    => $subject->code,
            'area_id' => $subject->area_id,
            'area'    => $subject->area ? ['id'=>$subject->area->id, 'name'=>$subject->area->name, 'code'=>$subject->area->code] : null,
        ];
    }

    public function update(Request $r, Subject $subject)
    {
        $data = $r->validate([
            'name'    => 'sometimes|required|string|max:100',
            'code'    => 'sometimes|required|string|max:50|unique:subjects,code,'.$subject->id,
            'area_id' => 'nullable|exists:educational_areas,id',
        ]);

        $subject->update($data);

        return $subject->load('area:id,name,code');
    }

    public function destroy(Subject $subject)
    {
        // если есть пивоты (subject_teacher), лучше сначала detach, либо FK ON DELETE CASCADE
        $subject->delete();
        return response()->json(['message' => 'Subject deleted']);
    }

}
