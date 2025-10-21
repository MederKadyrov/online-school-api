<?php

use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\GroupStudentsController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\GroupCourseController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmission;
use App\Http\Controllers\Auth\StudentRegisterWizardController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\GradeController;
use App\Http\Controllers\Teacher\GroupPickController;
use App\Http\Controllers\Teacher\LessonController;
use App\Http\Controllers\Teacher\ResourceController;
use App\Models\EducationalArea;
use App\Models\Lesson;
use App\Models\Level;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterStudentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Teacher\CourseController as TeacherCourseController;
use App\Http\Controllers\Teacher\StructureController as TeacherStructureController;
use App\Http\Controllers\Teacher\AssignmentController as TAssign;
use App\Http\Controllers\Student\AssignmentSubmissionController as SSubmit;
use App\Http\Controllers\Teacher\QuizController as TQuiz;
use App\Http\Controllers\Student\QuizController as SQuiz;
use App\Http\Controllers\Student\CourseController as SCourse;
use App\Http\Controllers\Student\ResourceController as SResource;
use App\Http\Controllers\Student\ProfileController as SProfile;
use App\Http\Controllers\Teacher\ProfileController as TProfile;


Route::middleware(['auth:sanctum'])->group(function () {

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
        $q = \App\Models\Grade::with(['course.subject','teacher.user','gradeable'])
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

        // Управление паролями пользователей
        Route::get('/users/passwords', [\App\Http\Controllers\Admin\UserPasswordController::class, 'index']);
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\UserPasswordController::class, 'resetPassword']);
        Route::post('/users/{user}/set-password', [\App\Http\Controllers\Admin\UserPasswordController::class, 'setPassword']);

        


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

        Route::get('/groups/{group}/courses', [GroupCourseController::class, 'index']);
        Route::post('/groups/{group}/courses-sync', [GroupCourseController::class, 'sync']);

        // Работы студентов (для мониторинга учителей)
        Route::get('/submissions', [AdminSubmission::class, 'index']);
        Route::get('/submissions/courses', [AdminSubmission::class, 'courses']);
        Route::get('/submissions/teachers', [AdminSubmission::class, 'teachers']);

        // Журнал оценок
        Route::get('/journal', [\App\Http\Controllers\Admin\JournalController::class, 'index']);
        Route::get('/journal/groups', [\App\Http\Controllers\Admin\JournalController::class, 'groups']);
        Route::get('/journal/courses', [\App\Http\Controllers\Admin\JournalController::class, 'courses']);
        Route::get('/journal/modules', [\App\Http\Controllers\Admin\JournalController::class, 'modules']);
        Route::get('/journal/grades/{grade}', [\App\Http\Controllers\Admin\JournalController::class, 'gradeDetails']);

        Route::get('/levels', function () {
            return Level::orderBy('number')->get(['id','number','title']);
        });

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

Route::middleware(['auth:sanctum','role:teacher'])->prefix('teacher')->group(function () {

    Route::get('/subjects', [TeacherCourseController::class, 'mySubjects']);


    // Курсы
    Route::get ('/courses',               [TeacherCourseController::class, 'index']);
    Route::post('/courses',               [TeacherCourseController::class, 'store']);
    Route::get ('/courses/{course}',      [TeacherCourseController::class, 'show'])->can('manage','course');
    Route::put ('/courses/{course}',      [TeacherCourseController::class, 'update'])->can('manage','course');
    Route::delete('/courses/{course}',    [TeacherCourseController::class, 'destroy'])->can('manage','course');

    // Привязка к группам
    Route::post('/courses/{course}/groups-sync', [TeacherCourseController::class,'syncGroups'])->can('manage','course');

    // Структура: модули/главы/параграфы/ресурсы (минимум)
    Route::post('/courses/{course}/modules',         [TeacherStructureController::class,'createModule'])->can('manage','course');

//    Route::post('/paragraphs/{paragraph}/resources', [TeacherStructureController::class,'createResource']);

    Route::post('/modules/{module}/reorder',         [TeacherStructureController::class,'reorderChapters']);

    Route::get ('/modules/{module}/chapters',        [TeacherStructureController::class,'listChapters']);
    Route::post('/modules/{module}/chapters',        [TeacherStructureController::class,'createChapter']);

    Route::post('/chapters/{chapter}/paragraphs',    [TeacherStructureController::class,'createParagraph']);
    Route::get ('/chapters/{chapter}/paragraphs',    [TeacherStructureController::class,'listParagraphs']);

    Route::post('/chapters/{chapter}/reorder',       [TeacherStructureController::class,'reorderParagraphs']);
    Route::put   ('/chapters/{chapter}',             [TeacherStructureController::class,'updateChapter']);
    Route::delete('/chapters/{chapter}',             [TeacherStructureController::class,'destroyChapter']);

    Route::put   ('/paragraphs/{paragraph}',         [TeacherStructureController::class,'updateParagraph']);
    Route::delete('/paragraphs/{paragraph}',         [TeacherStructureController::class,'destroyParagraph']);

    Route::post('/paragraphs/{paragraph}/reorder',   [TeacherStructureController::class,'reorderResources']);

    Route::get('/groups', [GroupPickController::class, 'index']);
    Route::post('/courses/{course}/groups-sync', [\App\Http\Controllers\Teacher\CourseController::class,'syncGroups'])
        ->can('manage','course');

    // CRUD ресурсов
//    Route::post('/paragraphs/{paragraph}/resources', [TeacherStructureController::class,'createResource']);

    Route::post   ('/paragraphs/{paragraph}/resources',      [ResourceController::class,'store']);
    Route::get    ('/paragraphs/{paragraph}/resources',      [ResourceController::class,'index']);
    Route::put    ('/resources/{resource}',                  [ResourceController::class,'update']);
    Route::delete ('/resources/{resource}',                  [ResourceController::class,'destroy']);

    // Загрузка файла и превью
    Route::post   ('/upload/resource-file',                  [ResourceController::class,'uploadFile']);

    // Журнал оценок учителя
    Route::get('/journal', [\App\Http\Controllers\Teacher\JournalController::class, 'index']);
    Route::get('/journal/groups', [\App\Http\Controllers\Teacher\JournalController::class, 'groups']);
    Route::get('/journal/courses', [\App\Http\Controllers\Teacher\JournalController::class, 'courses']);
    Route::get('/journal/modules', [\App\Http\Controllers\Teacher\JournalController::class, 'modules']);
    Route::get('/journal/grades/{grade}', [\App\Http\Controllers\Teacher\JournalController::class, 'gradeDetails']);
    Route::post('/journal/module-grades', [\App\Http\Controllers\Teacher\JournalController::class, 'storeModuleGrade']);
    Route::delete('/journal/module-grades/{id}', [\App\Http\Controllers\Teacher\JournalController::class, 'deleteModuleGrade']);

    //    Задания
    Route::post('/paragraphs/{paragraph}/assignments',   [TAssign::class,'store']);
    Route::get ('/assignments/{assignment}',             [TAssign::class,'show']);
    Route::put ('/assignments/{assignment}',             [TAssign::class,'update']);
    Route::post('/assignments/{assignment}/publish',     [TAssign::class,'publish']);
    Route::get ('/courses/{course}/assignments',         [TAssign::class,'assignmentsByCourse']);

    Route::get ('/assignments/{assignment}/submissions', [TAssign::class,'submissions']);
    Route::put ('/submissions/{submission}/grade',       [TAssign::class,'grade']);
    Route::get ('/submissions',                          [TAssign::class,'allSubmissions']);

    Route::post('/upload/assignment-attachment',         [TAssign::class,'uploadAttachment']);

    //    Тесты
    Route::post('/paragraphs/{paragraph}/quizzes',        [TQuiz::class,'store']);
    Route::get ('/quizzes/{quiz}',                        [TQuiz::class,'show']);
    Route::put ('/quizzes/{quiz}',                        [TQuiz::class,'update']);
    Route::post('/quizzes/{quiz}/publish',                [TQuiz::class,'publish']);

    Route::post('/quizzes/{quiz}/questions',              [TQuiz::class,'addQuestion']);
    Route::put ('/questions/{question}',                  [TQuiz::class,'updateQuestion']);
    Route::delete('/questions/{question}',                [TQuiz::class,'destroyQuestion']);

    Route::post('/questions/{question}/options',          [TQuiz::class,'addOption']);
    Route::put ('/options/{option}',                      [TQuiz::class,'updateOption']);
    Route::delete('/options/{option}',                    [TQuiz::class,'destroyOption']);

    //    редактирование заданий
    Route::delete('/assignments/{assignment}', [TAssign::class,'destroy']);      // удаление
    Route::get('/paragraphs/{paragraph}/assignment', [TAssign::class,'byParagraph']); // получить задание параграфа (если есть)

    Route::get('/paragraphs/{paragraph}/quiz', [TQuiz::class,'byParagraph']);

    // Профиль учителя
    Route::get('/profile', [TProfile::class, 'show']);
    Route::post('/change-password', [TProfile::class, 'changePassword']);
});

Route::middleware(['auth:sanctum','role:student'])->prefix('student')->group(function () {

    Route::get('/courses', [SCourse::class, 'index']);
    Route::get('/courses/{course}', [SCourse::class, 'show']);

    Route::get('/paragraphs/{paragraph}', [\App\Http\Controllers\Student\ParagraphController::class, 'show']);
    Route::get('/paragraphs/{paragraph}/resources', [SResource::class, 'index']);

    Route::get ('/paragraphs/{paragraph}/assignments',   [SSubmit::class,'listForParagraph']);
    Route::get ('/assignments/{assignment}/my',          [SSubmit::class,'mySubmission']);
    Route::post('/assignments/{assignment}/submit',      [SSubmit::class,'submit']); // multipart или JSON

    //    Тесты
    Route::get ('/paragraphs/{paragraph}/quiz',           [SQuiz::class,'getQuiz']);         // если опубликован
    Route::post('/quizzes/{quiz}/start',                  [SQuiz::class,'start']);
    Route::post('/attempts/{attempt}/answer',             [SQuiz::class,'answer']);          // по одному вопросу
    Route::post('/attempts/{attempt}/finish',             [SQuiz::class,'finish']);          // автопроверка
    Route::get ('/quizzes/{quiz}/my-attempts',            [SQuiz::class,'myAttempts']);

    // Журнал оценок студента
    Route::get('/journal', [\App\Http\Controllers\Student\JournalController::class, 'index']);
    Route::get('/journal/courses', [\App\Http\Controllers\Student\JournalController::class, 'courses']);
    Route::get('/journal/modules', [\App\Http\Controllers\Student\JournalController::class, 'modules']);

    // Профиль студента
    Route::get('/profile', [SProfile::class, 'show']);
    Route::post('/change-password', [SProfile::class, 'changePassword']);
});

Route::get   ('/admin/subjects',          [SubjectController::class,'index']);


// Публичные маршруты (без авторизации)
Route::post('/auth/login', [LoginController::class,'login']);

// список уровней (классов) (публичный)
Route::get('/levels', function () {
            return Level::orderBy('number')->get(['id','number','title']);
        });

// Регистрация студента (доступна для всех)
Route::post('/auth/register-student-validate', [StudentRegisterWizardController::class, 'validateOnly']);
Route::post('/auth/register-student', [StudentRegisterWizardController::class, 'createWithDocuments']);

// Восстановление пароля
Route::post('/auth/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetCode']);
Route::post('/auth/reset-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'resetPassword']);

// Защищенные маршруты авторизации
Route::middleware('auth:sanctum')->get('/auth/me', [LoginController::class,'me']);
Route::middleware('auth:sanctum')->post('/auth/logout', [LoginController::class,'logout']);
