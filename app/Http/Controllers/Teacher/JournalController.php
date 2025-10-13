<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Group;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleGrade;
use App\Models\Teacher;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /**
     * Получить данные журнала для учителя
     */
    public function index(Request $request)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        $groupId = $request->input('group_id');
        $courseId = $request->input('course_id');
        $moduleId = $request->input('module_id');

        if (!$groupId || !$courseId) {
            return response()->json(['message' => 'group_id и course_id обязательны'], 400);
        }

        // Проверяем, что курс принадлежит этому учителю
        $course = Course::where('id', $courseId)
            ->where('teacher_id', $teacher->id)
            ->with([
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
            ])->firstOrFail();

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
        $grades->load(['gradeable']);

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
                    'teacher_comment' => $moduleGrade->teacher_comment,
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
     * Получить список групп для фильтра (только группы учителя)
     */
    public function groups(Request $request)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        // Получаем группы, к которым привязаны курсы учителя
        $groups = Group::whereHas('courses', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
        ->with('level:id,number')
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
     * Получить список курсов для фильтра (только курсы учителя)
     */
    public function courses(Request $request)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        $courses = Course::where('teacher_id', $teacher->id)
            ->with(['subject:id,name', 'teacher.user:id,first_name,last_name,middle_name'])
            ->orderBy('subject_id')
            ->get(['id', 'subject_id', 'level_id', 'teacher_id']);

        return $courses->map(function($c) {
            $title = $c->subject->name . ' ' . $c->level_id . ' kl';

            return [
                'id' => $c->id,
                'display_name' => $title,
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
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $courseId = $request->input('course_id');

        if (!$courseId) {
            return response()->json(['message' => 'course_id обязателен'], 400);
        }

        // Проверяем, что курс принадлежит этому учителю
        Course::where('id', $courseId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

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
     * Создать или обновить модульную оценку
     */
    public function storeModuleGrade(Request $request)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'module_id' => 'required|exists:modules,id',
            'course_id' => 'required|exists:courses,id',
            'grade_5' => 'required|integer|min:2|max:5',
            'teacher_comment' => 'nullable|string|max:1000',
        ]);

        // Проверяем, что курс принадлежит этому учителю
        Course::where('id', $validated['course_id'])
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // Создаем или обновляем модульную оценку
        $moduleGrade = ModuleGrade::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'module_id' => $validated['module_id'],
            ],
            [
                'course_id' => $validated['course_id'],
                'teacher_id' => $teacher->id,
                'grade_5' => $validated['grade_5'],
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'graded_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Модульная оценка успешно сохранена',
            'module_grade' => [
                'id' => $moduleGrade->id,
                'grade' => $moduleGrade->grade_5,
                'graded_at' => $moduleGrade->graded_at,
                'teacher_comment' => $moduleGrade->teacher_comment,
            ]
        ]);
    }

    /**
     * Удалить модульную оценку
     */
    public function deleteModuleGrade(Request $request, $id)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        $moduleGrade = ModuleGrade::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $moduleGrade->delete();

        return response()->json([
            'message' => 'Модульная оценка успешно удалена'
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
}
