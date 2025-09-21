<?php

use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\GroupStudentsController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Auth\StudentRegisterWizardController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\GradeController;
use App\Http\Controllers\Teacher\LessonController;
use App\Models\EducationalArea;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterStudentController;
use App\Http\Controllers\Auth\LoginController;


// Вариант: только админ/сотрудник может регистрировать
Route::middleware(['auth:sanctum'])->group(function () {

    // Префлайт-валидация (Шаг 1), НИЧЕГО НЕ СОЗДАЁТ
    Route::post('/auth/register-student-validate', [StudentRegisterWizardController::class, 'validateOnly']);
    // Итоговое создание + загрузка документов (Шаг 2)
    Route::post('/auth/register-student', [StudentRegisterWizardController::class, 'createWithDocuments']);

    Route::get('/teacher/lessons', [LessonController::class,'index']);
    Route::post('/teacher/lessons', [LessonController::class,'store']);
    Route::patch('/teacher/lessons/{lesson}', [LessonController::class,'update']);
    Route::delete('/teacher/lessons/{lesson}', [LessonController::class,'destroy']);

    Route::get('/teacher/lessons/{lesson}/students', [AttendanceController::class, 'students']);
    Route::post('/teacher/attendance', [AttendanceController::class, 'store']);
    Route::get('/teacher/lessons/{lesson}/attendance', [AttendanceController::class,'index']);

    // учитель
    Route::get('/teacher/lessons/{lesson}/grades', [GradeController::class, 'index']);
    Route::post('/teacher/grades', [GradeController::class, 'store']);

    // ученик: свои оценки
    Route::get('/student/grades', function (\Illuminate\Http\Request $r) {
        abort_unless(auth()->user()->hasRole('student'), 403);
        $student = auth()->user()->student;
        $q = \App\Models\Grade::with(['lesson.subject','lesson.group','lesson.teacher.user'])
            ->where('student_id', $student->id)
            ->orderByDesc('graded_at');
        if ($r->filled('from')) $q->where('graded_at','>=',$r->input('from'));
        if ($r->filled('to'))   $q->where('graded_at','<=',$r->input('to'));
        return $q->paginate(20);
    });

    Route::get('/levels', function () {
        return \App\Models\Level::where('active', true)->orderBy('sort')->get(['id','number','title']);
    });

});

// ===== ADMIN API (простые CRUD) =====
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // только admin/staff
    Route::middleware('role:admin|staff')->group(function () {
        // Subjects
        Route::get   ('/subjects',          [SubjectController::class,'index']);
        Route::post  ('/subjects',          [SubjectController::class,'store']);
        Route::get   ('/subjects/{subject}',[SubjectController::class,'show']);
        Route::put   ('/subjects/{subject}',[SubjectController::class,'update']);
        Route::delete('/subjects/{subject}',[SubjectController::class,'destroy']);

        // Groups
        Route::get('/groups', [\App\Http\Controllers\Admin\GroupController::class, 'index']);
        Route::post('/groups', [\App\Http\Controllers\Admin\GroupController::class, 'store']);
        Route::post('/groups/{group}/attach-students', [\App\Http\Controllers\Admin\GroupController::class, 'attachStudents']);

        // Students (поиск/список для прикрепления)
        Route::get('/students', [\App\Http\Controllers\Admin\StudentController::class, 'index']);

        // Make teacher
        Route::post('/users/{user}/make-teacher', [\App\Http\Controllers\Admin\UserController::class, 'makeTeacher']);

        // Lessons (создание как админ)
        Route::post('/lessons', [\App\Http\Controllers\Admin\LessonController::class, 'store']);
    });

    Route::middleware('role:admin')->group(function () {
        // учителя
        Route::get('/teachers', [TeacherController::class, 'index']);
        Route::post('/teachers', [TeacherController::class, 'store']);
        Route::post('/teachers/{teacher}/subjects', [TeacherController::class, 'attachSubjects']);
        Route::get   ('/teachers/{id}',   [TeacherController::class,'show']);
        Route::put   ('/teachers/{id}',   [TeacherController::class,'update']);
        Route::delete('/teachers/{id}',   [TeacherController::class,'destroy']);

        Route::get('/levels', function () {
            return Level::orderBy('number')->get(['id','number','title']);
        });


        Route::get   ('/groups',          [GroupController::class,'index']);
        Route::post  ('/groups',          [GroupController::class,'store']);
        Route::get   ('/groups/{group}',  [GroupController::class,'show']);    // ← ДОБАВИЛИ
        Route::put   ('/groups/{group}',  [GroupController::class,'update']);
        Route::delete('/groups/{group}',  [GroupController::class,'destroy']); // ← ДОБАВИЛИ
        Route::post  ('/groups/{group}/attach-students', [GroupController::class,'attachStudents']);

//        назначение студентов в группу
        Route::get ('/groups/{group}/students', [GroupStudentsController::class,'listInGroup']);
        Route::get ('/students',                 [GroupStudentsController::class,'listForPickup']);
        Route::post('/groups/{group}/attach-students', [GroupStudentsController::class,'attach']);
        Route::post('/groups/{group}/detach-students', [GroupStudentsController::class,'detach']);
        Route::post('/groups/{group}/move-students',   [GroupStudentsController::class,'move']);

        Route::get('/educational-areas', fn () =>
            EducationalArea::orderBy('name')->get(['id','name','code'])
        );

    });
});


Route::middleware('auth:sanctum')->get('/student/lessons', function (Request $r) {
    abort_unless(auth()->user()->hasRole('student'), 403);
    $student = auth()->user()->student;
    $q = Lesson::with(['subject','teacher.user','group'])
        ->whereIn('group_id', $student->groups()->pluck('groups.id'));

    if ($r->filled('from')) $q->where('starts_at','>=',$r->input('from'));
    if ($r->filled('to'))   $q->where('starts_at','<=',$r->input('to'));

    return $q->orderBy('starts_at')->paginate(20);
});

Route::post('/auth/login', [LoginController::class,'login']);
Route::middleware('auth:sanctum')->get('/auth/me', [LoginController::class,'me']);
Route::middleware('auth:sanctum')->post('/auth/logout', [LoginController::class,'logout']);

