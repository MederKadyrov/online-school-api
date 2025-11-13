<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\RegisterStudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class StudentRegisterWizardController extends Controller
{
    /** Общие правила для Шага 1 (без файлов) */
    protected function rulesPersonal(): array
    {
        return [
            'guardian_type' => ['required', Rule::in(['parent','representative'])],

            'guardian' => ['required','array'],
            'guardian.last_name'   => ['required','string','max:60'],
            'guardian.first_name'  => ['required','string','max:60'],
            'guardian.middle_name' => ['nullable','string','max:60'],
            'guardian.sex'         => ['required', Rule::in(['male','female'])],
            'guardian.citizenship' => ['required','string','max:10'],
            'guardian.pin'         => ['required','regex:/^\d{14}$/','unique:users,pin'],
            'guardian.phone'       => ['required','string','max:30'],
            'guardian.address'     => ['required','string','max:255'],
            'guardian.email'       => ['required','email','max:255','unique:users,email'],

            'student' => ['required','array'],
            'student.last_name'    => ['required','string','max:60'],
            'student.first_name'   => ['required','string','max:60'],
            'student.middle_name'  => ['nullable','string','max:60'],
            'student.sex'          => ['required', Rule::in(['male','female'])],
            'student.citizenship'  => ['required','string','max:10'],
            'student.birth_date'   => ['required','date'],
            'student.level_id'     => ['required','exists:levels,id'], // уровень из справочника
            'student.pin'          => ['required','regex:/^\d{14}$/','unique:users,pin'],
            'student.phone'        => ['nullable','string','max:30'],
            'student.email'        => ['nullable','email','max:255','unique:users,email'],
            'student.password'     => ['required','string','min:8','confirmed'],
            'student.class_letter' => ['nullable','string','max:2'],
        ];
    }

    /** Префлайт-валидация (Шаг 1): проверяем данные, НИЧЕГО НЕ СОЗДАЁМ */
    public function validateOnly(Request $r)
    {
        $r->validate($this->rulesPersonal());
        return response()->noContent(); // 204
    }

    /** Итоговое создание + документы (Шаг 2): всё в одной транзакции */
    public function createWithDocuments(Request $r, RegisterStudentService $service)
    {
        // Валидируем и данные, и файлы
        $data = $r->validate($this->rulesPersonal() + [
                'student_photo'           => ['nullable','file','mimes:jpeg,jpg,png','max:4096'],
                'guardian_application'    => ['required','file','mimes:jpeg,jpg,pdf','max:8192'],
                'birth_certificate'       => ['required','file','mimes:jpeg,jpg,pdf','max:8192'],
                'student_pin_doc'         => ['required','file','mimes:jpeg,jpg,pdf','max:8192'],
                'guardian_passport'       => ['required','file','mimes:jpeg,jpg,pdf','max:8192'],
                'medical_certificate'     => ['required','file','mimes:jpeg,jpg,pdf','max:8192'],
                'previous_school_record'  => ['required','file','mimes:jpeg,jpg,pdf','max:8192'],
            ]);

        $student = DB::transaction(function () use ($data, $service, $r) {
            // 1) создаём пользователей (родитель/представитель и ученик) + запись students
            $student = $service->handle(
                $data['guardian'],
                $data['student'],
                $data['guardian_type']
            );

            // 1.5) сохраняем фото студента (если есть)
            if ($r->hasFile('student_photo')) {
                $photoPath = $r->file('student_photo')->store('user_photos', 'public');
                $student->user->update(['photo' => $photoPath]);
            }

            // 2) сохраняем документы (если есть)
            if ($r->hasFile(null)) { // есть любые файлы
                $doc = StudentDocument::firstOrCreate(['student_id' => $student->id]);
                $dir = "student_docs/{$student->id}";
                $map = [
                    'guardian_application'   => 'guardian_application_path',
                    'birth_certificate'      => 'birth_certificate_path',
                    'student_pin_doc'        => 'student_pin_doc_path',
                    'guardian_passport'      => 'guardian_passport_path',
                    'medical_certificate'    => 'medical_certificate_path',
                    'previous_school_record' => 'previous_school_record_path',
                ];
                foreach ($map as $input => $column) {
                    if ($r->hasFile($input)) {
                        $path = $r->file($input)->store($dir, 'local'); // storage/app/...
                        $doc->{$column} = $path;
                    }
                }
                $doc->save();
            }

            return $student;
        });

        return response()->json(['student_id' => $student->id, 'message' => 'created'], 201);
    }

//    не используется
    public function step1(Request $r, RegisterStudentService $service)
    {
        $data = $r->validate([
            'guardian_type' => ['required', Rule::in(['parent','representative'])],

            'guardian' => ['required','array'],
            'guardian.last_name'   => ['required','string','max:60'],
            'guardian.first_name'  => ['required','string','max:60'],
            'guardian.middle_name' => ['nullable','string','max:60'],
            'guardian.sex'         => ['required', Rule::in(['male','female'])],
            'guardian.citizenship' => ['required','string','max:10'],
            'guardian.pin'         => ['required','regex:/^\d{14}$/', 'unique:users,pin'],
            'guardian.phone'       => ['required','string','max:30'],
            'guardian.address'     => ['required','string','max:255'],
            'guardian.email'       => ['required','email','max:255','unique:users,email'],

            'student' => ['required','array'],
            'student.last_name'    => ['required','string','max:60'],
            'student.first_name'   => ['required','string','max:60'],
            'student.middle_name'  => ['nullable','string','max:60'],
            'student.sex'          => ['required', Rule::in(['male','female'])],
            'student.citizenship'  => ['required','string','max:10'],
            'student.birth_date'   => ['required','date'],

            // ── ключевая замена: level_id вместо grade ──
            'student.level_id'     => ['required','exists:levels,id'],

            'student.phone'        => ['nullable','string','max:30'],
            'student.email'        => ['nullable','email','max:255','unique:users,email'],
            'student.password'     => ['required','string','min:8','confirmed'],
            'student.class_letter' => ['nullable','string','max:2'],
            // 'student.pin' опционально, если вы его ведёте у учеников
        ]);

        // На случай legacy фронта: если прислали student.grade, конвертируем в level_id
        if (empty($data['student']['level_id']) && !empty($data['student']['grade'])) {
            $lvl = \App\Models\Level::where('number', (int)$data['student']['grade'])->first();
            abort_if(!$lvl, 422, 'Неверный grade — не найден level');
            $data['student']['level_id'] = $lvl->id;
        }

        $g = $data['guardian'];
        $s = $data['student'];

        $guardianPayload = [
            'last_name'   => $g['last_name'],
            'first_name'  => $g['first_name'],
            'middle_name' => $g['middle_name'] ?? null,
            'pin'         => $g['pin'],
            'citizenship' => $g['citizenship'],
            'phone'       => $g['phone'],
            'email'       => $g['email'],
            'sex'         => $g['sex'],           // male|female
            'address'     => $g['address'],
        ];

        $studentPayload = [
            'last_name'   => $s['last_name'],
            'first_name'  => $s['first_name'],
            'middle_name' => $s['middle_name'] ?? null,
            'pin'         => $s['pin'] ?? null,
            'citizenship' => $s['citizenship'],
            'phone'       => $s['phone'] ?? null,
            'email'       => $s['email'] ?? null,
            'sex'         => $s['sex'],           // male|female
            'birth_date'  => $s['birth_date'],

            // теперь level_id
            'level_id'    => $s['level_id'],

            'class_letter'=> $s['class_letter'] ?? null,
            'password'    => $s['password'],
        ];

        // Сервис регистрации должен записать users.{last,first,middle_name} и students.level_id
        $student = $service->handle($guardianPayload, $studentPayload, $data['guardian_type']);

        // ВАЖНО: на этом шаге НЕ прикрепляем к группе. Распределение — отдельный админ-процесс.
        return response()->json(['student_id' => $student->id], 201);
    }

    /**
     * Шаг 2 — Загрузка документов (multipart/form-data)
     * Поля: guardian_application, birth_certificate, student_pin_doc, guardian_passport, medical_certificate
     */

//    Не используется
    public function step2(Request $r)
    {
        $r->validate([
            'student_id' => ['required','exists:students,id'],
            'guardian_application' => ['nullable','file','mimes:jpeg,jpg,pdf','max:8192'],
            'birth_certificate'    => ['nullable','file','mimes:jpeg,jpg,pdf','max:8192'],
            'student_pin_doc'      => ['nullable','file','mimes:jpeg,jpg,pdf','max:8192'],
            'guardian_passport'    => ['nullable','file','mimes:jpeg,jpg,pdf','max:8192'],
            'medical_certificate'  => ['nullable','file','mimes:jpeg,jpg,pdf','max:8192'],
        ]);

        $student = Student::findOrFail($r->input('student_id'));

        $doc = StudentDocument::firstOrCreate(['student_id' => $student->id]);
        $dir = "student_docs/{$student->id}";

        $map = [
            'guardian_application' => 'guardian_application_path',
            'birth_certificate'    => 'birth_certificate_path',
            'student_pin_doc'      => 'student_pin_doc_path',
            'guardian_passport'    => 'guardian_passport_path',
            'medical_certificate'  => 'medical_certificate_path',
        ];

        foreach ($map as $input => $column) {
            if ($r->hasFile($input)) {
                $path = $r->file($input)->store($dir, 'local'); // storage/app/...
                $doc->{$column} = $path;
            }
        }

        $doc->save();

        return response()->json(['message' => 'ok', 'student_id' => $student->id], 201);
    }
}
