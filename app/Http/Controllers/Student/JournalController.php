<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Course;
use App\Models\ModuleGrade;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /**
     * Получить журнал оценок для студента
     */
    public function index(Request $request)
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();

        $courseId = $request->input('course_id');
        $moduleId = $request->input('module_id');

        if (!$courseId) {
            return response()->json(['message' => 'course_id обязателен'], 400);
        }

        // Получаем курс с модулями, главами и параграфами
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

        // Получаем все оценки студента по курсу
        // Для тестов берем только лучшую попытку
        $allGrades = Grade::where('course_id', $courseId)
            ->where('student_id', $student->id)
            ->get();

        // Разделяем оценки на тесты и задания
        $quizGrades = $allGrades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\QuizAttempt::class;
        });

        $assignmentGrades = $allGrades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\AssignmentSubmission::class;
        });

        // Загружаем gradeable для группировки
        $quizGrades->load('gradeable');

        // Группируем оценки тестов по quiz_id, берем лучшую
        $bestQuizGrades = collect();

        foreach ($quizGrades->groupBy(function($grade) {
            return $grade->gradeable->quiz_id ?? null;
        }) as $quizId => $attempts) {
            if ($quizId) {
                // Берем попытку с максимальным grade_5, если равны - с максимальным score
                $best = $attempts->sortByDesc(function($grade) {
                    return $grade->grade_5 * 10000 + $grade->score;
                })->first();

                $bestQuizGrades->push($best);
            }
        }

        // Объединяем лучшие оценки за тесты и все оценки за задания
        $grades = $bestQuizGrades->merge($assignmentGrades);

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
            ->where('student_id', $student->id)
            ->whereIn('module_id', $moduleIds)
            ->get();

        // Создаем структуру оценок по параграфам
        $gradesByParagraph = [];
        foreach ($paragraphStructure as $para) {
            $gradesByParagraph[$para['paragraph_id']] = [
                'assignment' => null,
                'quiz' => null,
            ];
        }

        // Заполняем оценки
        foreach ($grades as $grade) {
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
                    'title' => $grade->title,
                    'graded_at' => $grade->graded_at,
                    'teacher_comment' => $grade->teacher_comment,
                ];
            }
        }

        // Создаем структуру модульных оценок
        $gradesByModule = [];
        foreach ($moduleStructure as $mod) {
            $moduleGrade = $moduleGrades->where('module_id', $mod['module_id'])->first();

            $gradesByModule[$mod['module_id']] = $moduleGrade ? [
                'id' => $moduleGrade->id,
                'grade' => $moduleGrade->grade_5,
                'graded_at' => $moduleGrade->graded_at,
                'teacher_comment' => $moduleGrade->teacher_comment,
            ] : null;
        }

        return response()->json([
            'grades_by_paragraph' => $gradesByParagraph,
            'grades_by_module' => $gradesByModule,
            'paragraphs' => $paragraphStructure,
            'modules' => $moduleStructure,
            'average' => $grades->avg('grade_5'),
            'total_grades' => $grades->count(),
        ]);
    }

    /**
     * Получить список курсов студента
     */
    public function courses(Request $request)
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();

        // Получаем курсы группы студента
        $courses = Course::whereHas('groups', function($q) use ($student) {
            $q->where('groups.id', $student->group_id);
        })
        ->with(['subject:id,name', 'teacher.user:id,first_name,last_name,middle_name'])
        ->orderBy('subject_id')
        ->get(['id', 'subject_id', 'level_id', 'teacher_id']);

        return $courses->map(function($c) {
            $teacherName = $c->teacher && $c->teacher->user
                ? $c->teacher->user->name
                : '';

            $title = $c->subject->name . ' ' . $c->level_id . ' kl';

            return [
                'id' => $c->id,
                'display_name' => $title . ($teacherName ? " ({$teacherName})" : ''),
                'subject_name' => $c->subject->name,
                'teacher_name' => $teacherName,
            ];
        });
    }

    /**
     * Получить список модулей курса
     */
    public function modules(Request $request)
    {
        $student = Student::where('user_id', $request->user()->id)->firstOrFail();
        $courseId = $request->input('course_id');

        if (!$courseId) {
            return response()->json(['message' => 'course_id обязателен'], 400);
        }

        // Проверяем, что студент имеет доступ к этому курсу
        $hasAccess = Course::where('id', $courseId)
            ->whereHas('groups', function($q) use ($student) {
                $q->where('groups.id', $student->group_id);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Нет доступа к этому курсу'], 403);
        }

        $modules = \App\Models\Module::where('course_id', $courseId)
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
}
