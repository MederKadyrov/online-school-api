<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Paragraph;
use Illuminate\Http\Request;

class ParagraphController extends Controller
{
    /**
     * Получить информацию о параграфе с вложенными данными для breadcrumbs
     */
    public function show(Request $request, Paragraph $paragraph)
    {
        // Проверяем, что студент имеет доступ к этому параграфу
        $user = $request->user();
        abort_unless($user->hasRole('student'), 403);

        $student = $user->student;

        // Загружаем параграф с вложенными данными: chapter -> module -> course
        $paragraph->load([
            'chapter' => function ($query) {
                $query->select('id', 'module_id', 'title', 'number');
            },
            'chapter.module' => function ($query) {
                $query->select('id', 'course_id', 'title', 'number');
            },
            'chapter.module.course' => function ($query) {
                $query->select('id', 'title', 'subject_id', 'level_id');
            },
            'chapter.module.course.subject' => function ($query) {
                $query->select('id', 'name');
            },
            'chapter.module.course.level' => function ($query) {
                $query->select('id', 'number');
            }
        ]);

        // Проверяем, что студент имеет доступ к курсу
        $course = $paragraph->chapter?->module?->course;
        if (!$course) {
            abort(404, 'Параграф не найден или не привязан к курсу');
        }

        // Проверяем, что студент состоит в группе, к которой привязан курс
        $group = $student->group;
        $hasAccess = $group && $group->courses()->where('courses.id', $course->id)->exists();

        abort_unless($hasAccess, 403, 'У вас нет доступа к этому параграфу');

        return response()->json([
            'id' => $paragraph->id,
            'title' => $paragraph->title,
            'description' => $paragraph->description,
            'number' => $paragraph->number,
            'position' => $paragraph->position,
            'chapter' => [
                'id' => $paragraph->chapter->id,
                'title' => $paragraph->chapter->title,
                'number' => $paragraph->chapter->number,
                'module' => [
                    'id' => $paragraph->chapter->module->id,
                    'title' => $paragraph->chapter->module->title,
                    'number' => $paragraph->chapter->module->number,
                    'course' => [
                        'id' => $course->id,
                        'title' => $course->title,
                        'subject' => $course->subject ? [
                            'id' => $course->subject->id,
                            'name' => $course->subject->name,
                        ] : null,
                        'level' => $course->level ? [
                            'id' => $course->level->id,
                            'number' => $course->level->number,
                        ] : null,
                    ]
                ]
            ]
        ]);
    }
}
