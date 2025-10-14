<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Group;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleGrade;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /**
     * Получить данные журнала с фильтрацией
     */
    public function index(Request $request)
    {
        $groupId = $request->input('group_id');
        $courseId = $request->input('course_id');
        $moduleId = $request->input('module_id');

        if (!$groupId || !$courseId) {
            return response()->json(['message' => 'group_id и course_id обязательны'], 400);
        }

        // Получаем курс с его модулями, главами и параграфами
        $course = Course::with([
            'modules' => function($q) use ($moduleId) {
                if ($moduleId && $moduleId !== 'all') {
                    $q->where('id', $moduleId);
                }
                $q->orderBy('number');
            },
            'modules.chapters' => function($q) {
                $q->orderBy('number');
            },
            'modules.chapters.paragraphs' => function($q) {
                $q->orderBy('number');
            }
        ])->findOrFail($courseId);

        // Строим структуру параграфов и модулей
        $paragraphStructure = [];
        $moduleStructure = [];

        foreach ($course->modules as $module) {
            $moduleStructure[] = [
                'module_id' => $module->id,
                'module_number' => $module->number,
                'display_name' => 'М' . $this->getRomanNumeral($module->number),
                'title' => $module->title,
            ];

            foreach ($module->chapters as $chapter) {
                foreach ($chapter->paragraphs as $paragraph) {
                    $paragraphStructure[] = [
                        'paragraph_id' => $paragraph->id,
                        'module_id' => $module->id,
                        'module_number' => $module->number,
                        'chapter_number' => $chapter->number,
                        'paragraph_number' => $paragraph->number,
                        'display_name' => $this->getRomanNumeral($module->number) . '.' .
                                        $chapter->number . '.' .
                                        $paragraph->number,
                        'title' => $paragraph->title,
                    ];
                }
            }
        }

        // Получаем студентов группы
        $students = Student::where('group_id', $groupId)
            ->with('user:id,first_name,last_name,middle_name')
            ->orderBy('id')
            ->get(['id', 'user_id', 'group_id']);

        // Получаем все оценки для этих студентов и курса с eager loading
        $grades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get();

        // Загружаем gradeable с вложенными отношениями
        $grades->load([
            'gradeable' => function ($query) {
                $query->withoutGlobalScopes();
            }
        ]);

        // Загружаем вложенные отношения для QuizAttempt
        $quizAttemptIds = $grades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\QuizAttempt::class;
        })->pluck('gradeable_id');

        if ($quizAttemptIds->isNotEmpty()) {
            \App\Models\QuizAttempt::whereIn('id', $quizAttemptIds)
                ->with('quiz.paragraph')
                ->get()
                ->keyBy('id')
                ->each(function($attempt) use ($grades) {
                    $grades->where('gradeable_id', $attempt->id)
                        ->where('gradeable_type', \App\Models\QuizAttempt::class)
                        ->each(function($grade) use ($attempt) {
                            $grade->setRelation('gradeable', $attempt);
                        });
                });
        }

        // Загружаем вложенные отношения для AssignmentSubmission
        $submissionIds = $grades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\AssignmentSubmission::class;
        })->pluck('gradeable_id');

        if ($submissionIds->isNotEmpty()) {
            \App\Models\AssignmentSubmission::whereIn('id', $submissionIds)
                ->with('assignment.paragraph')
                ->get()
                ->keyBy('id')
                ->each(function($submission) use ($grades) {
                    $grades->where('gradeable_id', $submission->id)
                        ->where('gradeable_type', \App\Models\AssignmentSubmission::class)
                        ->each(function($grade) use ($submission) {
                            $grade->setRelation('gradeable', $submission);
                        });
                });
        }

        // Получаем модульные оценки
        $moduleIds = collect($moduleStructure)->pluck('module_id');
        $moduleGrades = ModuleGrade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('module_id', $moduleIds)
            ->get();

        // Группируем оценки по студентам и параграфам
        $journalData = $students->map(function ($student) use ($grades, $paragraphStructure, $moduleGrades, $moduleStructure) {
            $studentGrades = $grades->where('student_id', $student->id);

            // Создаем структуру оценок по параграфам
            $gradesByParagraph = [];
            foreach ($paragraphStructure as $para) {
                $gradesByParagraph[$para['paragraph_id']] = [
                    'assignment' => null,
                    'quiz' => null,
                ];
            }

            // Заполняем оценки
            foreach ($studentGrades as $grade) {
                $paragraphId = null;
                $type = null;

                if ($grade->gradeable instanceof \App\Models\QuizAttempt && $grade->gradeable->quiz) {
                    $paragraphId = $grade->gradeable->quiz->paragraph_id;
                    $type = 'quiz';
                } elseif ($grade->gradeable instanceof \App\Models\AssignmentSubmission && $grade->gradeable->assignment) {
                    $paragraphId = $grade->gradeable->assignment->paragraph_id;
                    $type = 'assignment';
                }

                if ($paragraphId && isset($gradesByParagraph[$paragraphId]) && $type) {
                    $gradesByParagraph[$paragraphId][$type] = [
                        'id' => $grade->id,
                        'grade' => $grade->grade_5,
                        'score' => $grade->score,
                        'max_points' => $grade->max_points,
                    ];
                }
            }

            // Создаем структуру модульных оценок
            $gradesByModule = [];
            foreach ($moduleStructure as $mod) {
                $moduleGrade = $moduleGrades->where('student_id', $student->id)
                    ->where('module_id', $mod['module_id'])
                    ->first();

                $gradesByModule[$mod['module_id']] = $moduleGrade ? [
                    'id' => $moduleGrade->id,
                    'grade' => $moduleGrade->grade_5,
                    'graded_at' => $moduleGrade->graded_at,
                ] : null;
            }

            return [
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'grades_by_paragraph' => $gradesByParagraph,
                'grades_by_module' => $gradesByModule,
                'average' => $studentGrades->avg('grade_5'),
            ];
        });

        return response()->json([
            'students' => $journalData,
            'paragraphs' => $paragraphStructure,
            'modules' => $moduleStructure,
            'summary' => [
                'total_students' => $students->count(),
                'total_grades' => $grades->count(),
                'average_grade' => $grades->avg('grade_5'),
            ]
        ]);
    }

    /**
     * Конвертировать число в римское
     */
    private function getRomanNumeral($number)
    {
        $map = [
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
        ];
        $result = '';
        foreach ($map as $value => $roman) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }
        return $result;
    }

    /**
     * Получить список групп для фильтра
     */
    public function groups()
    {
        $groups = Group::with('level:id,number')
            ->orderBy('level_id')
            ->orderBy('class_letter')
            ->get(['id', 'class_letter', 'level_id']);

        return $groups->map(function($g) {
            return [
                'id' => $g->id,
                'display_name' => $g->display_name,
            ];
        });
    }

    /**
     * Получить список курсов для фильтра
     */
    public function courses()
    {
        $courses = Course::with(['subject:id,name', 'teacher.user:id,first_name,last_name,middle_name'])
            ->orderBy('subject_id')
            ->get(['id', 'subject_id', 'level_id', 'teacher_id']);

        return $courses->map(function($c) {
            $teacherName = $c->teacher && $c->teacher->user
                ? mb_substr($c->teacher->user->name, 0, 1) . '.' .
                  implode('.', array_map(fn($part) => mb_substr($part, 0, 1),
                    array_slice(explode(' ', $c->teacher->user->name), 1))) . '.'
                : '';

            $title = $c->subject->name . ' ' . $c->level_id . ' kl';
            $displayName = $teacherName ? "{$title} ({$teacherName})" : $title;

            return [
                'id' => $c->id,
                'display_name' => $displayName,
                'subject_id' => $c->subject_id,
                'level_id' => $c->level_id,
                'teacher_id' => $c->teacher_id,
            ];
        });
    }

    /**
     * Получить список модулей курса для фильтра
     */
    public function modules(Request $request)
    {
        $courseId = $request->input('course_id');

        if (!$courseId) {
            return response()->json(['message' => 'course_id обязателен'], 400);
        }

        $modules = Module::where('course_id', $courseId)
            ->orderBy('number')
            ->get(['id', 'title', 'number']);

        return $modules->map(function($m) {
            return [
                'id' => $m->id,
                'display_name' => "М{$m->number} → {$m->title}",
            ];
        });
    }

    /**
     * Получить детали конкретной оценки
     */
    public function gradeDetails($gradeId)
    {
        $grade = Grade::with([
            'student.user:id,first_name,last_name,middle_name',
            'student.group.level:id,number',
            'course.subject:id,name',
            'teacher.user:id,first_name,last_name,middle_name',
            'gradeable'
        ])->findOrFail($gradeId);

        // Дополнительная информация в зависимости от типа
        $details = [
            'id' => $grade->id,
            'student_name' => $grade->student->user->name,
            'group' => $grade->student->group->display_name,
            'subject' => $grade->course->subject->name,
            'teacher' => $grade->teacher && $grade->teacher->user ? $grade->teacher->user->name : 'Автоматическая проверка',
            'title' => $grade->title,
            'score' => $grade->score,
            'max_points' => $grade->max_points,
            'grade_5' => $grade->grade_5,
            'teacher_comment' => $grade->teacher_comment,
            'graded_at' => $grade->graded_at,
            'type' => class_basename($grade->gradeable_type),
        ];

        // Если это тест - добавляем информацию о попытке
        if ($grade->gradeable instanceof \App\Models\QuizAttempt) {
            $attempt = $grade->gradeable;
            $details['quiz'] = [
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
                'autograded' => $attempt->autograded,
                'answers_count' => $attempt->answers()->count(),
            ];
        }

        // Если это задание - добавляем информацию о сдаче
        if ($grade->gradeable instanceof \App\Models\AssignmentSubmission) {
            $submission = $grade->gradeable;
            $details['assignment'] = [
                'submitted_at' => $submission->submitted_at,
                'status' => $submission->status,
                'has_file' => !empty($submission->file_path),
                'text_answer' => $submission->text_answer,
            ];
        }

        return response()->json($details);
    }
}
