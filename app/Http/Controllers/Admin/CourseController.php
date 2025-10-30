<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Список всех курсов с информацией об учителях
     */
    public function index(Request $r)
    {
        $q = Course::with(['subject', 'teacher.user', 'level', 'groups.level'])
            ->orderByDesc('id');

        // Фильтрация по предмету
        if ($r->filled('subject_id')) {
            $q->where('subject_id', $r->input('subject_id'));
        }

        // Фильтрация по учителю
        if ($r->filled('teacher_id')) {
            $q->where('teacher_id', $r->input('teacher_id'));
        }

        // Фильтрация по уровню
        if ($r->filled('level_id')) {
            $q->where('level_id', $r->input('level_id'));
        }

        // Фильтрация по группе
        if ($r->filled('group_id')) {
            $q->whereHas('groups', function($gq) use ($r) {
                $gq->where('groups.id', $r->input('group_id'));
            });
        }

        // Поиск по названию
        if ($r->filled('search')) {
            $term = '%' . $r->input('search') . '%';
            $q->where('title', 'like', $term);
        }

        $perPage = $r->input('per_page', 50);
        $courses = $q->paginate($perPage);

        $data = $courses->getCollection()->map(function($course) {
            $teacher = $course->teacher;
            $teacherName = $teacher && $teacher->user
                ? trim("{$teacher->user->last_name} {$teacher->user->first_name} {$teacher->user->middle_name}")
                : 'Не назначен';

            return [
                'id' => $course->id,
                'title' => $course->title,
                'subject' => [
                    'id' => $course->subject->id ?? null,
                    'name' => $course->subject->name ?? '',
                ],
                'teacher' => [
                    'id' => $teacher->id ?? null,
                    'name' => $teacherName,
                ],
                'level' => [
                    'id' => $course->level->id ?? null,
                    'number' => $course->level->number ?? null,
                    'title' => $course->level->title ?? '',
                ],
                'groups' => $course->groups->map(function($group) {
                    return [
                        'id' => $group->id,
                        'display_name' => $group->level->number . $group->class_letter,
                    ];
                }),
                'modules_count' => $course->modules()->count(),
                'created_at' => $course->created_at->format('Y-m-d'),
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $courses->currentPage(),
            'last_page' => $courses->lastPage(),
            'per_page' => $courses->perPage(),
            'total' => $courses->total(),
        ]);
    }

    /**
     * Детальная информация о курсе со всей структурой
     */
    public function show($id)
    {
        $course = Course::with([
            'subject',
            'teacher.user',
            'level',
            'groups.level',
            'modules',
            'modules.chapters',
            'modules.chapters.paragraphs',
            'modules.chapters.paragraphs.assignment.submissions',
            'modules.chapters.paragraphs.quiz.questions.options',
            'modules.chapters.paragraphs.resources',
        ])->findOrFail($id);

        $teacher = $course->teacher;
        $teacherName = $teacher && $teacher->user
            ? trim("{$teacher->user->last_name} {$teacher->user->first_name} {$teacher->user->middle_name}")
            : 'Не назначен';

        return response()->json([
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'subject' => [
                'id' => $course->subject->id ?? null,
                'name' => $course->subject->name ?? '',
            ],
            'teacher' => [
                'id' => $teacher->id ?? null,
                'name' => $teacherName,
            ],
            'level' => [
                'id' => $course->level->id ?? null,
                'number' => $course->level->number ?? null,
                'title' => $course->level->title ?? '',
            ],
            'groups' => $course->groups->map(function($group) {
                return [
                    'id' => $group->id,
                    'display_name' => $group->level->number . $group->class_letter,
                ];
            }),
            'modules' => $course->modules->map(function($module) {
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'number' => $module->number,
                    'position' => $module->position,
                    'chapters' => $module->chapters->map(function($chapter) {
                        return [
                            'id' => $chapter->id,
                            'title' => $chapter->title,
                            'number' => $chapter->number,
                            'position' => $chapter->position,
                            'paragraphs' => $chapter->paragraphs->map(function($paragraph) {
                                return [
                                    'id' => $paragraph->id,
                                    'title' => $paragraph->title,
                                    'number' => $paragraph->number,
                                    'position' => $paragraph->position,
                                    'description' => $paragraph->description,
                                    'has_assignment' => $paragraph->assignment !== null,
                                    'has_quiz' => $paragraph->quiz !== null,
                                    'assignment' => $paragraph->assignment ? [
                                        'id' => $paragraph->assignment->id,
                                        'title' => $paragraph->assignment->title,
                                        'instructions' => $paragraph->assignment->instructions,
                                        'due_at' => $paragraph->assignment->due_at?->format('Y-m-d H:i'),
                                        'max_points' => $paragraph->assignment->max_points,
                                        'status' => $paragraph->assignment->status,
                                        'submissions_count' => $paragraph->assignment->submissions->count(),
                                        'attachments_path' => $paragraph->assignment->attachments_path,
                                        'has_attachments' => !empty($paragraph->assignment->attachments_path),
                                    ] : null,
                                    'quiz' => $paragraph->quiz ? [
                                        'id' => $paragraph->quiz->id,
                                        'title' => $paragraph->quiz->title,
                                        'instructions' => $paragraph->quiz->instructions,
                                        'time_limit_sec' => $paragraph->quiz->time_limit_sec,
                                        'max_attempts' => $paragraph->quiz->max_attempts,
                                        'max_points' => $paragraph->quiz->max_points,
                                        'shuffle' => $paragraph->quiz->shuffle,
                                        'status' => $paragraph->quiz->status,
                                        'questions_count' => $paragraph->quiz->questions->count(),
                                        'questions' => $paragraph->quiz->questions->map(function($question) {
                                            return [
                                                'id' => $question->id,
                                                'type' => $question->type,
                                                'text' => $question->text,
                                                'points' => $question->points,
                                                'position' => $question->position,
                                                'options' => $question->options->map(function($option) {
                                                    return [
                                                        'id' => $option->id,
                                                        'text' => $option->text,
                                                        'is_correct' => $option->is_correct,
                                                        'position' => $option->position,
                                                    ];
                                                }),
                                            ];
                                        }),
                                    ] : null,
                                    'resources_count' => $paragraph->resources->count(),
                                    'resources' => $paragraph->resources->map(function($resource) {
                                        return [
                                            'id' => $resource->id,
                                            'title' => $resource->title,
                                            'type' => $resource->type,
                                            'url' => $resource->url,
                                            'path' => $resource->path,
                                            'position' => $resource->position,
                                        ];
                                    }),
                                ];
                            }),
                        ];
                    }),
                ];
            }),
            'created_at' => $course->created_at->format('Y-m-d H:i'),
            'updated_at' => $course->updated_at->format('Y-m-d H:i'),
        ]);
    }
}
