<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('student'), 403);





        $student = $user->student;
        if (!$student) return response()->json([]);

        // студент в одной группе
        $groupId = $student->group_id ?: ($student->group->id ?? null);
        if (!$groupId) return response()->json([]);

        $courses = \App\Models\Course::with([
                'subject:id,name',
                'level:id,number',
                'teacher.user:id,last_name,first_name,middle_name',
            ])
            ->whereHas('groups', fn($q) => $q->where('groups.id', $groupId))
            ->orderBy('title')
            ->get()
            ->map(function ($c) {
                $u = $c->teacher?->user;
                return [
                    'id'      => $c->id,
                    'title'   => $c->title,
                    'subject' => $c->subject ? ['name'=>$c->subject->name] : null,
                    'level'   => $c->level ? ['number'=>$c->level->number] : null,
                    'teacher' => $u ? ['name'=>trim(implode(' ', array_filter([$u->last_name,$u->first_name,$u->middle_name])))] : null,
                ];
            });

        return response()->json($courses);



//
//        $student = $user->student;
//        if (!$student) {
//            return response()->json([]);
//        }
//
//        // ✅ студент принадлежит только одной группе
//        $groupId = $student->group_id ?: ($student->group->id ?? null);
//        if (!$groupId) {
//            return response()->json([]);
//        }
//
//        // Грузим только нужные связи и только данные по этой группе
//        $courses = Course::with([
//                'subject:id,name,code',
//                'level:id,number,title',
//                'teacher.user:id,last_name,first_name,middle_name',
//                // подгружаем только текущую группу + её level
//                'groups' => function ($q) use ($groupId) {
//                    $q->where('groups.id', $groupId)
//                      ->with(['level:id,number,title']);
//                },
//                'modules' => function ($q) {
//                    $q->orderBy('position')->with([
//                        'chapters' => function ($chapters) {
//                            $chapters->orderBy('position')->with([
//                                'paragraphs' => function ($paragraphs) {
//                                    $paragraphs->orderBy('position');
//                                }
//                            ]);
//                        }
//                    ]);
//                },
//            ])
//            ->whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))
//            ->orderBy('title')
//            ->get()
//            ->map(function (Course $course) use ($groupId) {
//                $teacherUser = $course->teacher?->user;
//
//                // В ответ возвращаем только одну (текущую) группу
//                $courseGroups = $course->groups->map(function ($group) {
//                    $level   = $group->level;
//                    $display = $level
//                        ? ($level->number . ($group->class_letter ? '-' . $group->class_letter : ''))
//                        : ($group->display_name ?? '');
//                    return [
//                        'id'   => $group->id,
//                        'name' => trim($display) ?: ('Группа #' . $group->id),
//                    ];
//                })->values();
//
//                return [
//                    'id'      => $course->id,
//                    'title'   => $course->title,
//                    'subject' => $course->subject ? [
//                        'id'   => $course->subject->id,
//                        'name' => $course->subject->name,
//                        'code' => $course->subject->code,
//                    ] : null,
//                    'level'   => $course->level ? [
//                        'id'     => $course->level->id,
//                        'number' => $course->level->number,
//                        'title'  => $course->level->title,
//                    ] : null,
//                    'teacher' => $teacherUser ? [
//                        'id'   => $course->teacher->id,
//                        'name' => trim(implode(' ', array_filter([
//                            $teacherUser->last_name,
//                            $teacherUser->first_name,
//                            $teacherUser->middle_name,
//                        ]))),
//                    ] : null,
//                    'groups'  => $courseGroups, // будет максимум один элемент
//                    'modules' => $course->modules->map(function ($module) {
//                        return [
//                            'id'     => $module->id,
//                            'number' => $module->number,
//                            'title'  => $module->title,
//                            'chapters' => $module->chapters->map(function ($chapter) {
//                                return [
//                                    'id'     => $chapter->id,
//                                    'number' => $chapter->number,
//                                    'title'  => $chapter->title,
//                                    'paragraphs' => $chapter->paragraphs->map(function ($paragraph) {
//                                        return [
//                                            'id'          => $paragraph->id,
//                                            'number'      => $paragraph->number,
//                                            'title'       => $paragraph->title,
//                                            'description' => $paragraph->description,
//                                        ];
//                                    }),
//                                ];
//                            }),
//                        ];
//                    }),
//                ];
//            });
//
//        return response()->json($courses);
    }


    public function show(Request $request, \App\Models\Course $course)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('student'), 403);

        $student = $user->student;
        $groupId = $student?->group_id ?: ($student?->group->id ?? null);
        abort_unless($groupId, 403);

        // доступ только если курс привязан к группе студента
        $isAllowed = $course->groups()->where('groups.id', $groupId)->exists();
        abort_unless($isAllowed, 404);

        $course->load([
            'subject:id,name,code',
            'level:id,number,title',
            'teacher.user:id,last_name,first_name,middle_name',
            'modules' => function ($q) {
                $q->orderBy('position')->with([
                    'chapters' => function ($chapters) {
                        $chapters->orderBy('position')->with([
                            'paragraphs' => fn ($paras) => $paras->orderBy('position'),
                        ]);
                    }
                ]);
            },
        ]);

        $u = $course->teacher?->user;
        return response()->json([
            'id'      => $course->id,
            'title'   => $course->title,
            'subject' => $course->subject ? ['id'=>$course->subject->id,'name'=>$course->subject->name,'code'=>$course->subject->code] : null,
            'level'   => $course->level ? ['id'=>$course->level->id,'number'=>$course->level->number,'title'=>$course->level->title] : null,
            'teacher' => $u ? ['id'=>$course->teacher->id,'name'=>trim(implode(' ', array_filter([$u->last_name,$u->first_name,$u->middle_name])))] : null,
            'modules' => $course->modules->map(function ($m) {
                return [
                    'id'=>$m->id,'number'=>$m->number,'title'=>$m->title,
                    'chapters'=>$m->chapters->map(function ($ch) {
                        return [
                            'id'=>$ch->id,'number'=>$ch->number,'title'=>$ch->title,
                            'paragraphs'=>$ch->paragraphs->map(fn($p)=>[
                                'id'=>$p->id,'number'=>$p->number,'title'=>$p->title,'description'=>$p->description,
                            ]),
                        ];
                    }),
                ];
            }),
        ]);


    }

}
