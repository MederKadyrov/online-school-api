<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Course, Grade, Student, Teacher, YearlyGrade, ExamGrade, FinalGrade};
use Illuminate\Http\Request;

class CourseGradeController extends Controller
{
    /**
     * Получить учителя из запроса
     */
    private function getTeacher(Request $request)
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->first();
        abort_unless($teacher, 403, 'Только преподаватели могут выставлять оценки');
        return $teacher;
    }

    /**
     * Получить все финальные оценки для курса
     * GET /api/teacher/courses/{course}/final-grades
     */
    public function index(Request $request, Course $course)
    {
        $teacher = $this->getTeacher($request);

        // Проверяем, что учитель ведет этот курс
        abort_unless($course->teacher_id === $teacher->id, 403, 'Вы не ведете этот курс');

        $groupId = $request->input('group_id');

        // Получаем студентов курса (из конкретной группы или всех групп)
        $studentsQuery = Student::whereHas('group.courses', function($q) use ($course) {
            $q->where('courses.id', $course->id);
        });

        if ($groupId) {
            $studentsQuery->where('group_id', $groupId);
        }

        $students = $studentsQuery->with('user:id,first_name,last_name,middle_name')
            ->orderBy('id')
            ->get();

        // Получаем все финальные оценки (годовые, экзаменационные, итоговые)
        $grades = Grade::where('course_id', $course->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('gradeable_type', [YearlyGrade::class, ExamGrade::class, FinalGrade::class])
            ->get()
            ->groupBy('student_id');

        // Формируем ответ
        $data = $students->map(function($student) use ($grades) {
            $studentGrades = $grades->get($student->id, collect());

            return [
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'yearly_grade' => $studentGrades->where('gradeable_type', YearlyGrade::class)->first(),
                'exam_grade' => $studentGrades->where('gradeable_type', ExamGrade::class)->first(),
                'final_grade' => $studentGrades->where('gradeable_type', FinalGrade::class)->first(),
            ];
        });

        return response()->json([
            'students' => $data,
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'level_id' => $course->level_id,
                'has_exam_grades' => in_array($course->level_id, [9, 11]),
            ],
        ]);
    }

    /**
     * Выставить годовую оценку
     * POST /api/teacher/courses/{course}/yearly-grade
     */
    public function storeYearlyGrade(Request $request, Course $course)
    {
        $teacher = $this->getTeacher($request);
        abort_unless($course->teacher_id === $teacher->id, 403);

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'grade_5' => 'required|integer|min:2|max:5',
            'teacher_comment' => 'nullable|string|max:1000',
        ]);

        // Проверяем, что студент учится в группе, которая изучает этот курс
        $student = Student::findOrFail($validated['student_id']);
        $hasAccess = $student->group->courses()->where('courses.id', $course->id)->exists();
        abort_unless($hasAccess, 403, 'Студент не изучает этот курс');

        // Создаем или обновляем годовую оценку
        $grade = Grade::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'course_id' => $course->id,
                'gradeable_type' => YearlyGrade::class,
                'gradeable_id' => $course->id,
            ],
            [
                'teacher_id' => $teacher->id,
                'grade_5' => $validated['grade_5'],
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'title' => 'Годовая оценка',
                'max_points' => null,
                'graded_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Годовая оценка выставлена',
            'grade' => $grade,
        ]);
    }

    /**
     * Выставить экзаменационную оценку
     * POST /api/teacher/courses/{course}/exam-grade
     */
    public function storeExamGrade(Request $request, Course $course)
    {
        $teacher = $this->getTeacher($request);
        abort_unless($course->teacher_id === $teacher->id, 403);

        // Проверяем, что экзаменационные оценки можно выставлять только для 9 и 11 классов
        abort_unless(
            in_array($course->level_id, [9, 11]),
            403,
            'Экзаменационные оценки можно выставлять только для 9 и 11 классов'
        );

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'grade_5' => 'required|integer|min:2|max:5',
            'teacher_comment' => 'nullable|string|max:1000',
        ]);

        // Проверяем, что студент учится в группе, которая изучает этот курс
        $student = Student::findOrFail($validated['student_id']);
        $hasAccess = $student->group->courses()->where('courses.id', $course->id)->exists();
        abort_unless($hasAccess, 403, 'Студент не изучает этот курс');

        // Создаем или обновляем экзаменационную оценку
        $grade = Grade::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'course_id' => $course->id,
                'gradeable_type' => ExamGrade::class,
                'gradeable_id' => $course->id,
            ],
            [
                'teacher_id' => $teacher->id,
                'grade_5' => $validated['grade_5'],
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'title' => 'Экзаменационная оценка',
                'max_points' => null,
                'graded_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Экзаменационная оценка выставлена',
            'grade' => $grade,
        ]);
    }

    /**
     * Выставить итоговую оценку
     * POST /api/teacher/courses/{course}/final-grade
     */
    public function storeFinalGrade(Request $request, Course $course)
    {
        $teacher = $this->getTeacher($request);
        abort_unless($course->teacher_id === $teacher->id, 403);

        // Проверяем, что итоговые оценки можно выставлять только для 9 и 11 классов
        abort_unless(
            in_array($course->level_id, [9, 11]),
            403,
            'Итоговые оценки можно выставлять только для 9 и 11 классов'
        );

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'grade_5' => 'required|integer|min:2|max:5',
            'teacher_comment' => 'nullable|string|max:1000',
        ]);

        // Проверяем, что студент учится в группе, которая изучает этот курс
        $student = Student::findOrFail($validated['student_id']);
        $hasAccess = $student->group->courses()->where('courses.id', $course->id)->exists();
        abort_unless($hasAccess, 403, 'Студент не изучает этот курс');

        // Создаем или обновляем итоговую оценку
        $grade = Grade::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'course_id' => $course->id,
                'gradeable_type' => FinalGrade::class,
                'gradeable_id' => $course->id,
            ],
            [
                'teacher_id' => $teacher->id,
                'grade_5' => $validated['grade_5'],
                'teacher_comment' => $validated['teacher_comment'] ?? null,
                'title' => 'Итоговая оценка',
                'max_points' => null,
                'graded_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Итоговая оценка выставлена',
            'grade' => $grade,
        ]);
    }

    /**
     * Удалить финальную оценку (годовую, экзаменационную или итоговую)
     * DELETE /api/teacher/final-grades/{grade}
     */
    public function destroy(Request $request, Grade $grade)
    {
        $teacher = $this->getTeacher($request);

        // Проверяем, что это финальная оценка
        abort_unless(
            in_array($grade->gradeable_type, [YearlyGrade::class, ExamGrade::class, FinalGrade::class]),
            403,
            'Можно удалять только финальные оценки'
        );

        // Проверяем, что учитель выставил эту оценку
        abort_unless($grade->teacher_id === $teacher->id, 403, 'Вы не можете удалить чужую оценку');

        $gradeType = class_basename($grade->gradeable_type);
        $grade->delete();

        return response()->json([
            'message' => "Оценка типа {$gradeType} удалена",
        ]);
    }

}
