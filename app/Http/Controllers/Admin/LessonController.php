<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function store(Request $r)
    {
        $data = $r->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'group_id'   => 'required|exists:groups,id',
            'starts_at'  => 'required|date|before:ends_at',
            'ends_at'    => 'required|date|after:starts_at',
            'meeting_provider' => 'nullable|in:jitsi,bbb,zoom,meet,custom',
            'meeting_url'      => 'nullable|url|max:255',
        ]);

        $lesson = Lesson::create($data);
        return response()->json($lesson->load(['subject','teacher.user','group']), 201);
    }
}
