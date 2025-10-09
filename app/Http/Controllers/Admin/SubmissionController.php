<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    /** Получить все отправки заданий с фильтрами (для администратора) */
    public function index(Request $r)
    {
        // Базовый запрос: все отправки заданий
        $query = AssignmentSubmission::query()
            ->with([
                'student.user:id,last_name,first_name,middle_name,email',
                'student.group:id,class_letter,level_id',
                'student.group.level:id,number',
                'assignment.paragraph.chapter.module.course:id,title',
                'assignment.paragraph.chapter.module.course.teacher.user:id,last_name,first_name,middle_name'
            ]);

        // Фильтр по статусу (submitted, returned)
        if ($r->filled('status')) {
            $query->where('status', $r->string('status'));
        }

        // Фильтр по курсу
        if ($r->filled('course_id')) {
            $query->whereHas('assignment.paragraph.chapter.module.course', function($q) use ($r) {
                $q->where('courses.id', $r->integer('course_id'));
            });
        }

        // Фильтр по группе
        if ($r->filled('group_id')) {
            $query->whereHas('student', function($q) use ($r) {
                $q->where('group_id', $r->integer('group_id'));
            });
        }

        // Фильтр по учителю
        if ($r->filled('teacher_id')) {
            $query->whereHas('assignment.paragraph.chapter.module.course', function($q) use ($r) {
                $q->where('teacher_id', $r->integer('teacher_id'));
            });
        }

        // Фильтр по конкретному заданию
        if ($r->filled('assignment_id')) {
            $query->where('assignment_id', $r->integer('assignment_id'));
        }

        // Поиск по студенту (имя)
        if ($r->filled('student_search')) {
            $search = $r->string('student_search')->trim();
            $query->whereHas('student.user', function($q) use ($search) {
                $q->where(function($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('middle_name', 'like', "%{$search}%");
                });
            });
        }

        $submissions = $query->orderByDesc('submitted_at')->orderByDesc('id')->get();

        return $submissions->map(function($s) {
            $u = $s->student?->user;
            $group = $s->student?->group;
            $course = $s->assignment?->paragraph?->chapter?->module?->course;
            $teacher = $course?->teacher;
            $teacherUser = $teacher?->user;

            return [
                'id'              => $s->id,
                'student'         => $u ? [
                    'id'   => $s->student_id,
                    'name' => trim(implode(' ', array_filter([$u->last_name, $u->first_name, $u->middle_name]))),
                    'email'=> $u->email
                ] : null,
                'group'           => $group ? [
                    'id'   => $group->id,
                    'name' => $group->display_name
                ] : null,
                'course'          => $course ? [
                    'id'    => $course->id,
                    'title' => $course->title
                ] : null,
                'teacher'         => $teacherUser ? [
                    'id'   => $teacher->id,
                    'name' => trim(implode(' ', array_filter([$teacherUser->last_name, $teacherUser->first_name, $teacherUser->middle_name])))
                ] : null,
                'assignment'      => [
                    'id'    => $s->assignment_id,
                    'title' => $s->assignment?->title
                ],
                'submitted_at'    => $s->submitted_at,
                'file_path'       => $s->file_path,
                'text_answer'     => $s->text_answer,
                'score'           => $s->score,
                'grade_5'         => $s->grade_5,
                'status'          => $s->status,
                'teacher_comment' => $s->teacher_comment,
            ];
        });
    }

    /** Получить список всех курсов (для фильтра) */
    public function courses(Request $r)
    {
        $query = \App\Models\Course::with(['teacher.user:id,last_name,first_name,middle_name', 'level:id,number']);

        // Фильтр по учителю (если передан)
        if ($r->filled('teacher_id')) {
            $query->where('teacher_id', $r->integer('teacher_id'));
        }

        return $query->orderBy('title')->get()->map(function($c) {
            $teacher = $c->teacher;
            $teacherUser = $teacher?->user;

            // Формат: "Иванов И.И."
            $teacherName = null;
            if ($teacherUser) {
                $lastName = $teacherUser->last_name ?? '';
                $firstInitial = $teacherUser->first_name ? mb_substr($teacherUser->first_name, 0, 1) . '.' : '';
                $middleInitial = $teacherUser->middle_name ? mb_substr($teacherUser->middle_name, 0, 1) . '.' : '';
                $teacherName = trim("{$lastName} {$firstInitial}{$middleInitial}");
            }

            // Заменяем "класс" на "кл"
            $title = str_replace('класс', 'кл', $c->title);

            // Формат отображения: "Физика 8 кл (Петрашов И.И.)"
            $displayName = $teacherName ? "{$title} ({$teacherName})" : $title;

            return [
                'id' => $c->id,
                'title' => $c->title,
                'display_name' => $displayName,
                'teacher_id' => $teacher?->id,
                'teacher_name' => $teacherName
            ];
        });
    }

    /** Получить список всех учителей (для фильтра) */
    public function teachers(Request $r)
    {
        return \App\Models\Teacher::with('user:id,last_name,first_name,middle_name')
            ->get()
            ->map(function($t) {
                $u = $t->user;
                return [
                    'id' => $t->id,
                    'name' => $u ? trim(implode(' ', array_filter([$u->last_name, $u->first_name, $u->middle_name]))) : 'Без имени'
                ];
            });
    }
}
