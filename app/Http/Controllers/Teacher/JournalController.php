<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Group;
use App\Models\Course;
use App\Models\Module;
use App\Models\Teacher;
use App\Models\YearlyGrade;
use App\Models\ExamGrade;
use App\Models\FinalGrade;
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

        // Получаем все оценки для этих студентов и курса
        // Для тестов берем только лучшую попытку каждого студента
        $allGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
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

        // Группируем оценки тестов по студенту и quiz_id, берем лучшую
        $bestQuizGrades = collect();
        foreach ($quizGrades->groupBy('student_id') as $studentId => $studentGrades) {
            foreach ($studentGrades->groupBy(function($grade) {
                return $grade->gradeable->quiz_id ?? null;
            }) as $quizId => $attempts) {
                if ($quizId) {
                    // Берем попытку с максимальным grade_5, если равны - с максимальным score из gradeable
                    $best = $attempts->sortByDesc(function($grade) {
                        $score = $grade->gradeable ? $grade->gradeable->score : 0;
                        return $grade->grade_5 * 10000 + $score;
                    })->first();

                    $bestQuizGrades->push($best);
                }
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
        $moduleGrades = Grade::where('course_id', $courseId)
            ->where('gradeable_type', Module::class)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('gradeable_id', $moduleIds)
            ->get();

        // Получаем финальные оценки (годовые, экзаменационные, итоговые)
        $finalGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('gradeable_type', [YearlyGrade::class, ExamGrade::class, FinalGrade::class])
            ->get()
            ->groupBy('student_id');

        // Группируем оценки по студентам и параграфам
        $journalData = $students->map(function ($student) use ($grades, $paragraphStructure, $moduleGrades, $moduleStructure, $finalGrades) {
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
                    // Получаем score из gradeable (QuizAttempt или AssignmentSubmission)
                    $score = $grade->gradeable ? $grade->gradeable->score : null;

                    $gradesByParagraph[$paragraphId][$type] = [
                        'id' => $grade->id,
                        'grade' => $grade->grade_5,
                        'score' => $score,
                        'max_points' => $grade->max_points,
                    ];
                }
            }

            // Создаем структуру модульных оценок
            $gradesByModule = [];
            foreach ($moduleStructure as $mod) {
                $moduleGrade = $moduleGrades->where('student_id', $student->id)
                    ->where('gradeable_id', $mod['module_id'])
                    ->first();

                $gradesByModule[$mod['module_id']] = $moduleGrade ? [
                    'id' => $moduleGrade->id,
                    'grade' => $moduleGrade->grade_5,
                    'graded_at' => $moduleGrade->graded_at,
                    'teacher_comment' => $moduleGrade->teacher_comment,
                ] : null;
            }

            // Получаем финальные оценки студента
            $studentFinalGrades = $finalGrades->get($student->id, collect());

            $yearlyGrade = $studentFinalGrades->where('gradeable_type', YearlyGrade::class)->first();
            $examGrade = $studentFinalGrades->where('gradeable_type', ExamGrade::class)->first();
            $finalGrade = $studentFinalGrades->where('gradeable_type', FinalGrade::class)->first();

            return [
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'grades_by_paragraph' => $gradesByParagraph,
                'grades_by_module' => $gradesByModule,
                'yearly_grade' => $yearlyGrade ? [
                    'id' => $yearlyGrade->id,
                    'grade' => $yearlyGrade->grade_5,
                    'graded_at' => $yearlyGrade->graded_at,
                    'teacher_comment' => $yearlyGrade->teacher_comment,
                ] : null,
                'exam_grade' => $examGrade ? [
                    'id' => $examGrade->id,
                    'grade' => $examGrade->grade_5,
                    'graded_at' => $examGrade->graded_at,
                    'teacher_comment' => $examGrade->teacher_comment,
                ] : null,
                'final_grade' => $finalGrade ? [
                    'id' => $finalGrade->id,
                    'grade' => $finalGrade->grade_5,
                    'graded_at' => $finalGrade->graded_at,
                    'teacher_comment' => $finalGrade->teacher_comment,
                ] : null,
                'average' => $studentGrades->avg('grade_5'),
            ];
        });

        return response()->json([
            'students' => $journalData,
            'paragraphs' => $paragraphStructure,
            'modules' => $moduleStructure,
            'course' => [
                'id' => $course->id,
                'level_id' => $course->level_id,
                'has_exam_grades' => in_array($course->level_id, [9, 11]),
            ],
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

        // Получаем название модуля для title
        $module = Module::find($validated['module_id']);

        // Создаем или обновляем модульную оценку в таблице grades
        $moduleGrade = Grade::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'gradeable_type' => Module::class,
                'gradeable_id' => $validated['module_id'],
            ],
            [
                'course_id' => $validated['course_id'],
                'teacher_id' => $teacher->id,
                'grade_5' => $validated['grade_5'],
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'title' => $module ? "Модульная оценка: {$module->title}" : 'Модульная оценка',
                'max_points' => null,
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

        $moduleGrade = Grade::where('id', $id)
            ->where('gradeable_type', Module::class)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $moduleGrade->delete();

        return response()->json([
            'message' => 'Модульная оценка успешно удалена'
        ]);
    }

    /**
     * Получить детали конкретной оценки
     */
    public function gradeDetails(Request $request, $gradeId)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        $grade = Grade::with([
            'student.user:id,first_name,last_name,middle_name',
            'student.group.level:id,number',
            'course.subject:id,name',
            'teacher.user:id,first_name,last_name,middle_name',
            'gradeable'
        ])->findOrFail($gradeId);

        // Проверяем, что оценка принадлежит курсу этого учителя
        if ($grade->course->teacher_id !== $teacher->id) {
            abort(403, 'Нет доступа к этой оценке');
        }

        // Дополнительная информация в зависимости от типа
        // Получаем score из gradeable (QuizAttempt или AssignmentSubmission)
        $score = $grade->gradeable ? $grade->gradeable->score : null;

        $details = [
            'id' => $grade->id,
            'student_name' => $grade->student->user->name,
            'group' => $grade->student->group->display_name,
            'subject' => $grade->course->subject->name,
            'teacher' => $grade->teacher && $grade->teacher->user ? $grade->teacher->user->name : 'Автоматическая проверка',
            'title' => $grade->title,
            'score' => $score,
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

    /**
     * Экспорт журнала в CSV
     * GET /api/teacher/journal/export
     */
    public function export(Request $request)
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
                'modules.chapters.paragraphs',
                'subject:id,name',
                'level:id,number'
            ])->firstOrFail();

        $group = Group::with('level:id,number')->findOrFail($groupId);

        // Получаем студентов группы
        $students = Student::where('group_id', $groupId)
            ->with('user:id,first_name,last_name,middle_name')
            ->orderBy('id')
            ->get();

        // Строим структуру параграфов
        $paragraphStructure = [];
        $moduleStructure = [];

        foreach ($course->modules as $module) {
            $moduleStructure[] = [
                'module_id' => $module->id,
                'module_number' => $module->number,
                'display_name' => 'М' . $this->getRomanNumeral($module->number),
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
                    ];
                }
            }
        }

        // Получаем все оценки
        $allGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get();

        $quizGrades = $allGrades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\QuizAttempt::class;
        });

        $assignmentGrades = $allGrades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\AssignmentSubmission::class;
        });

        $quizGrades->load('gradeable.quiz');

        // Группируем оценки тестов по quiz_id, берем лучшую
        $bestQuizGrades = collect();
        foreach ($quizGrades->groupBy('student_id') as $studentId => $studentGrades) {
            foreach ($studentGrades->groupBy(function($grade) {
                return $grade->gradeable->quiz_id ?? null;
            }) as $quizId => $attempts) {
                if ($quizId) {
                    $best = $attempts->sortByDesc(function($grade) {
                        $score = $grade->gradeable ? $grade->gradeable->score : 0;
                        return $grade->grade_5 * 10000 + $score;
                    })->first();
                    $bestQuizGrades->push($best);
                }
            }
        }

        $grades = $bestQuizGrades->merge($assignmentGrades);
        $assignmentGrades->load('gradeable.assignment');

        // Получаем модульные оценки
        $moduleIds = collect($moduleStructure)->pluck('module_id');
        $moduleGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->where('gradeable_type', Module::class)
            ->whereIn('gradeable_id', $moduleIds)
            ->get();

        // Получаем финальные оценки
        $finalGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('gradeable_type', [YearlyGrade::class, ExamGrade::class, FinalGrade::class])
            ->get()
            ->groupBy('student_id');

        // Формируем CSV
        $csv = [];

        // Заголовок
        $header = ['№', 'Студент'];
        foreach ($paragraphStructure as $para) {
            $header[] = $para['display_name'] . ' (З)';
            $header[] = $para['display_name'] . ' (Т)';
        }
        foreach ($moduleStructure as $mod) {
            $header[] = $mod['display_name'] . ' (МО)';
        }
        $header[] = 'Средний балл';
        $header[] = 'Годовая';
        if (in_array($course->level_id, [9, 11])) {
            $header[] = 'Экзамен';
            $header[] = 'Итоговая';
        }

        $csv[] = $header;

        // Данные студентов
        foreach ($students as $index => $student) {
            $row = [$index + 1, $student->user->name];

            $studentGrades = $grades->where('student_id', $student->id);

            // Оценки по параграфам
            foreach ($paragraphStructure as $para) {
                $assignmentGrade = null;
                $quizGrade = null;

                foreach ($studentGrades as $grade) {
                    $paragraphId = null;

                    if ($grade->gradeable instanceof \App\Models\QuizAttempt && $grade->gradeable->quiz) {
                        $paragraphId = $grade->gradeable->quiz->paragraph_id;
                        if ($paragraphId == $para['paragraph_id']) {
                            $quizGrade = $grade->grade_5;
                        }
                    } elseif ($grade->gradeable instanceof \App\Models\AssignmentSubmission && $grade->gradeable->assignment) {
                        $paragraphId = $grade->gradeable->assignment->paragraph_id;
                        if ($paragraphId == $para['paragraph_id']) {
                            $assignmentGrade = $grade->grade_5;
                        }
                    }
                }

                $row[] = $assignmentGrade ?? '';
                $row[] = $quizGrade ?? '';
            }

            // Модульные оценки
            foreach ($moduleStructure as $mod) {
                $moduleGrade = $moduleGrades->where('student_id', $student->id)
                    ->where('gradeable_id', $mod['module_id'])
                    ->first();
                $row[] = $moduleGrade ? $moduleGrade->grade_5 : '';
            }

            // Средний балл
            $row[] = $studentGrades->avg('grade_5') ? number_format($studentGrades->avg('grade_5'), 2) : '';

            // Финальные оценки
            $studentFinalGrades = $finalGrades->get($student->id, collect());
            $yearlyGrade = $studentFinalGrades->where('gradeable_type', YearlyGrade::class)->first();
            $examGrade = $studentFinalGrades->where('gradeable_type', ExamGrade::class)->first();
            $finalGrade = $studentFinalGrades->where('gradeable_type', FinalGrade::class)->first();

            $row[] = $yearlyGrade ? $yearlyGrade->grade_5 : '';
            if (in_array($course->level_id, [9, 11])) {
                $row[] = $examGrade ? $examGrade->grade_5 : '';
                $row[] = $finalGrade ? $finalGrade->grade_5 : '';
            }

            $csv[] = $row;
        }

        // Генерируем CSV файл
        $filename = sprintf(
            'journal_%s_%s_%s.csv',
            $course->subject->name,
            $group->display_name,
            date('Y-m-d')
        );

        $handle = fopen('php://temp', 'r+');

        // Добавляем BOM для правильной кодировки в Excel
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        foreach ($csv as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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
