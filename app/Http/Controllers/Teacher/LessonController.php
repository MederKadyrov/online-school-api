<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonStoreRequest;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $r)
    {
        $this->authorize('viewAny', Lesson::class);

        $q = Lesson::with(['subject','teacher.user','group']);

        if ($r->filled('from')) $q->where('starts_at','>=',$r->input('from'));
        if ($r->filled('to'))   $q->where('starts_at','<=',$r->input('to'));

        // Для teacher показываем только свои
        if (auth()->user()->hasRole('teacher')) {
            $q->where('teacher_id', auth()->user()->teacher->id ?? 0);
        }

        // Для student показываем только по его группам — переносим в отдельный контроллер student, ниже
        return $q->orderBy('starts_at')->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LessonStoreRequest $r)
    {
        // Учитель может создавать только свои уроки; админ/стфф — любые
        if (auth()->user()->hasRole('teacher')) {
            abort_unless(
                (int)$r->teacher_id === (int)(auth()->user()->teacher->id ?? 0),
                403, 'Можно создавать только свои занятия'
            );
        }
        $lesson = Lesson::create($r->validated());
        return response()->json($lesson->load(['subject','teacher.user','group']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LessonStoreRequest $r, Lesson $lesson)
    {
        $this->authorize('update', $lesson);
        $lesson->update($r->validated());
        return $lesson->load(['subject','teacher.user','group']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson)
    {
        $this->authorize('delete', $lesson);
        $lesson->delete();
        return response()->noContent();
    }
}
