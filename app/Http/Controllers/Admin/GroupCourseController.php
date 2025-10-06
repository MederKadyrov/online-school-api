<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupCourseController extends Controller
{
    public function index(Request $request, Group $group)
    {
        $group->load('level');

        $assignedCourses = $group->courses()
            ->with(['subject:id,name,code', 'level:id,number,title', 'teacher.user:id,last_name,first_name,middle_name'])
            ->orderBy('title')
            ->get()
            ->map(fn (Course $course) => $this->serializeCourse($course));

        $coursesQuery = Course::query()
            ->with(['subject:id,name,code', 'level:id,number,title', 'teacher.user:id,last_name,first_name,middle_name'])
            ->orderBy('title');

        if ($request->boolean('same_level', true) && $group->level_id) {
            $coursesQuery->where('level_id', $group->level_id);
        }

        if ($request->filled('level_id')) {
            $coursesQuery->where('level_id', (int) $request->input('level_id'));
        }

        if ($request->filled('search')) {
            $needle = trim($request->string('search'));
            $coursesQuery->where(function ($q) use ($needle) {
                $q->where('title', 'like', "%{$needle}%")
                    ->orWhereHas('subject', fn ($qq) => $qq->where('name', 'like', "%{$needle}%"))
                    ->orWhereHas('teacher.user', function ($qq) use ($needle) {
                        $qq->where('last_name', 'like', "%{$needle}%")
                            ->orWhere('first_name', 'like', "%{$needle}%")
                            ->orWhere('middle_name', 'like', "%{$needle}%");
                    });
            });
        }

        $availableCourses = $coursesQuery->limit(200)->get()->map(fn (Course $course) => $this->serializeCourse($course));

        return response()->json([
            'group' => [
                'id' => $group->id,
                'display_name' => $group->display_name,
                'level' => $group->level ? [
                    'id' => $group->level->id,
                    'number' => $group->level->number,
                    'title' => $group->level->title,
                ] : null,
            ],
            'selected_course_ids' => $assignedCourses->pluck('id')->all(),
            'assigned' => $assignedCourses,
            'available' => $availableCourses,
        ]);
    }

    public function sync(Request $request, Group $group)
    {
        $payload = $request->validate([
            'course_ids' => 'array',
            'course_ids.*' => 'integer|exists:courses,id',
        ]);

        $courseIds = collect($payload['course_ids'] ?? [])->unique()->values();

        if ($courseIds->isNotEmpty() && $group->level_id) {
            $mismatched = Course::whereIn('id', $courseIds)
                ->where('level_id', '!=', $group->level_id)
                ->pluck('id')
                ->all();

            if ($mismatched) {
                return response()->json([
                    'message' => 'Некоторые курсы не соответствуют уровню группы',
                    'invalid_ids' => $mismatched,
                ], 422);
            }
        }

        $group->courses()->sync($courseIds->all());

        return response()->json([
            'message' => 'Связи обновлены',
            'course_ids' => $courseIds->all(),
        ]);
    }

    private function serializeCourse(Course $course): array
    {
        $teacherUser = $course->teacher?->user;

        return [
            'id' => $course->id,
            'title' => $course->title,
            'subject' => $course->subject ? [
                'id' => $course->subject->id,
                'name' => $course->subject->name,
                'code' => $course->subject->code,
            ] : null,
            'level' => $course->level ? [
                'id' => $course->level->id,
                'number' => $course->level->number,
                'title' => $course->level->title,
            ] : null,
            'teacher' => $teacherUser ? [
                'id' => $course->teacher->id,
                'user_id' => $teacherUser->id,
                'name' => trim(implode(' ', array_filter([
                    $teacherUser->last_name,
                    $teacherUser->first_name,
                    $teacherUser->middle_name,
                ]))),
            ] : null,
        ];
    }
}
