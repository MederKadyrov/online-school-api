<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Group;
use App\Models\Course;
use App\Models\Module;
use App\Models\YearlyGrade;
use App\Models\ExamGrade;
use App\Models\FinalGrade;
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
                    // Берем попытку с максимальным grade_5, если равны - с максимальным score
                    $best = $attempts->sortByDesc(function($grade) {
                        $score = $grade->gradeable ? $grade->gradeable->score : 0;
                        return $grade->grade_5 * 10000 + $score; // сортировка по оценке, потом по баллам
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
            ->whereIn('student_id', $students->pluck('id'))
            ->where('gradeable_type', Module::class)
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
    public function courses(Request $request)
    {
        $groupId = $request->input('group_id');

        $query = Course::with(['subject:id,name', 'teacher.user:id,first_name,last_name,middle_name'])
            ->orderBy('subject_id');

        // Фильтруем курсы по группе, если указана
        if ($groupId) {
            $query->whereHas('groups', function($q) use ($groupId) {
                $q->where('groups.id', $groupId);
            });
        }

        $courses = $query->get(['id', 'subject_id', 'level_id', 'teacher_id', 'title']);

        return $courses->map(function($c) {
            $teacherName = $c->teacher && $c->teacher->user
                ? mb_substr($c->teacher->user->name, 0, 1) . '.' .
                  implode('.', array_map(fn($part) => mb_substr($part, 0, 1),
                    array_slice(explode(' ', $c->teacher->user->name), 1))) . '.'
                : '';

            $title = $c->title ?: ($c->subject->name . ' ' . $c->level_id . ' kl');
            $displayName = $teacherName ? "{$title} ({$teacherName})" : $title;

            return [
                'id' => $c->id,
                'display_name' => $displayName,
                'title' => $title,
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
     * Экспорт журнала в CSV формат (совместим с Excel)
     */
    public function export(Request $request)
    {
        $groupId = $request->input('group_id');
        $courseId = $request->input('course_id');
        $moduleId = $request->input('module_id');

        if (!$groupId || !$courseId) {
            return response()->json(['message' => 'group_id и course_id обязательны'], 400);
        }

        // Получаем данные журнала (используем ту же логику что и в index)
        $journalData = $this->getJournalData($groupId, $courseId, $moduleId);

        // Получаем информацию о группе и курсе для заголовка
        $group = Group::with('level')->find($groupId);
        $course = Course::with(['subject', 'teacher.user'])->find($courseId);

        $groupName = $group ? ($group->level->number . $group->class_letter) : '';
        $subjectName = $course->subject->name ?? '';
        $teacherName = $course->teacher && $course->teacher->user
            ? $course->teacher->user->last_name . ' ' .
              mb_substr($course->teacher->user->first_name, 0, 1) . '.' .
              ($course->teacher->user->middle_name ? mb_substr($course->teacher->user->middle_name, 0, 1) . '.' : '')
            : '';

        // Формируем CSV
        $filename = "journal_{$groupName}_{$subjectName}_" . date('Y-m-d') . ".csv";

        $callback = function() use ($journalData, $groupName, $subjectName, $teacherName) {
            $file = fopen('php://output', 'w');

            // Устанавливаем BOM для корректного отображения кириллицы в Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Заголовок файла
            fputcsv($file, ["Группа: $groupName"]);
            fputcsv($file, ["Предмет: $subjectName"]);
            fputcsv($file, ["Преподаватель: $teacherName"]);
            fputcsv($file, []); // Пустая строка

            // Шапка таблицы - строка 1: Модули
            $header1 = ['№', 'Студент'];
            foreach ($journalData['modules'] as $module) {
                $paragraphCount = count($journalData['paragraphs_by_module'][$module['module_id']] ?? []);
                $colspan = $paragraphCount * 2 + 1; // З + Т для каждого параграфа + МО
                $header1[] = $module['display_name'];
                for ($i = 1; $i < $colspan; $i++) {
                    $header1[] = ''; // Пустые ячейки для объединения
                }
            }
            $header1[] = 'Средний балл';
            fputcsv($file, $header1);

            // Шапка таблицы - строка 2: Параграфы
            $header2 = ['', ''];
            foreach ($journalData['modules'] as $module) {
                $paragraphs = $journalData['paragraphs_by_module'][$module['module_id']] ?? [];
                foreach ($paragraphs as $paragraph) {
                    // Дублируем название параграфа в обеих ячейках (З и Т)
                    $header2[] = $paragraph['display_name'];
                    $header2[] = $paragraph['display_name'];
                }
                $header2[] = 'МО';
            }
            $header2[] = '';
            fputcsv($file, $header2);

            // Шапка таблицы - строка 3: З/Т
            $header3 = ['', ''];
            foreach ($journalData['modules'] as $module) {
                $paragraphs = $journalData['paragraphs_by_module'][$module['module_id']] ?? [];
                foreach ($paragraphs as $paragraph) {
                    $header3[] = 'З';
                    $header3[] = 'Т';
                }
                $header3[] = '';
            }
            $header3[] = '';
            fputcsv($file, $header3);

            // Данные студентов
            foreach ($journalData['students'] as $index => $student) {
                $row = [$index + 1, $student['student_name']];

                foreach ($journalData['modules'] as $module) {
                    $paragraphs = $journalData['paragraphs_by_module'][$module['module_id']] ?? [];
                    foreach ($paragraphs as $paragraph) {
                        $assignmentGrade = $student['grades'][$paragraph['paragraph_id']]['assignment'] ?? '';
                        $quizGrade = $student['grades'][$paragraph['paragraph_id']]['quiz'] ?? '';
                        $row[] = $assignmentGrade;
                        $row[] = $quizGrade;
                    }
                    $row[] = $student['module_grades'][$module['module_id']] ?? '';
                }

                $row[] = number_format($student['average_grade'] ?? 0, 2);
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Вспомогательный метод для получения данных журнала
     */
    private function getJournalData($groupId, $courseId, $moduleId)
    {
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

        $paragraphStructure = [];
        $moduleStructure = [];
        $paragraphsByModule = [];

        foreach ($course->modules as $module) {
            $moduleStructure[] = [
                'module_id' => $module->id,
                'module_number' => $module->number,
                'display_name' => "М{$module->number} → {$module->title}",
            ];

            $paragraphsByModule[$module->id] = [];

            foreach ($module->chapters as $chapter) {
                foreach ($chapter->paragraphs as $paragraph) {
                    $displayName = "{$chapter->number}.{$paragraph->number}";

                    $paragraphStructure[] = [
                        'paragraph_id' => $paragraph->id,
                        'module_id' => $module->id,
                        'chapter_number' => $chapter->number,
                        'paragraph_number' => $paragraph->number,
                        'display_name' => $displayName,
                        'title' => $paragraph->title,
                    ];

                    $paragraphsByModule[$module->id][] = [
                        'paragraph_id' => $paragraph->id,
                        'display_name' => $displayName,
                        'title' => $paragraph->title,
                    ];
                }
            }
        }

        $students = Student::where('group_id', $groupId)
            ->with('user:id,first_name,last_name,middle_name')
            ->get();

        $paragraphIds = collect($paragraphStructure)->pluck('paragraph_id');

        $allGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get();

        $quizGrades = $allGrades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\QuizAttempt::class;
        });

        $assignmentGrades = $allGrades->filter(function($grade) {
            return $grade->gradeable_type === \App\Models\AssignmentSubmission::class;
        });

        // Загружаем gradeable для обеих коллекций до merge
        $quizGrades->load('gradeable');
        $assignmentGrades->load('gradeable');

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

        $gradesByStudent = $grades->groupBy('student_id');

        $moduleGrades = Grade::where('course_id', $courseId)
            ->whereIn('student_id', $students->pluck('id'))
            ->where('gradeable_type', Module::class)
            ->get()
            ->groupBy('student_id');

        $studentsData = [];

        foreach ($students as $student) {
            $user = $student->user;
            $studentName = $user ? trim("{$user->last_name} {$user->first_name} {$user->middle_name}") : 'Без имени';

            $studentGrades = $gradesByStudent->get($student->id, collect());

            $gradesByParagraph = [];
            foreach ($studentGrades as $grade) {
                $paragraphId = null;

                if ($grade->gradeable_type === \App\Models\QuizAttempt::class) {
                    $paragraphId = $grade->gradeable->quiz->paragraph_id ?? null;
                } elseif ($grade->gradeable_type === \App\Models\AssignmentSubmission::class) {
                    $paragraphId = $grade->gradeable->assignment->paragraph_id ?? null;
                }

                if (!$paragraphId || !$paragraphIds->contains($paragraphId)) {
                    continue;
                }

                if (!isset($gradesByParagraph[$paragraphId])) {
                    $gradesByParagraph[$paragraphId] = [
                        'assignment' => null,
                        'quiz' => null,
                    ];
                }

                if ($grade->gradeable_type === \App\Models\QuizAttempt::class) {
                    $gradesByParagraph[$paragraphId]['quiz'] = $grade->grade_5;
                } else {
                    $gradesByParagraph[$paragraphId]['assignment'] = $grade->grade_5;
                }
            }

            $studentModuleGrades = $moduleGrades->get($student->id, collect());
            $moduleGradesMap = [];
            foreach ($studentModuleGrades as $mg) {
                $moduleGradesMap[$mg->gradeable_id] = $mg->grade_5;
            }

            $allGradeValues = $studentGrades->pluck('grade_5')->filter()->values();
            $averageGrade = $allGradeValues->isNotEmpty() ? $allGradeValues->average() : null;

            $studentsData[] = [
                'student_id' => $student->id,
                'student_name' => $studentName,
                'grades' => $gradesByParagraph,
                'module_grades' => $moduleGradesMap,
                'average_grade' => $averageGrade,
            ];
        }

        return [
            'students' => $studentsData,
            'paragraphs' => $paragraphStructure,
            'modules' => $moduleStructure,
            'paragraphs_by_module' => $paragraphsByModule,
        ];
    }
}
