<?php
namespace App\Services;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterStudentService
{
    // helper: склейка ФИО
    private function fullName(array $p): string
    {
        return trim(implode(' ', array_filter([
            $p['last_name']   ?? null,
            $p['first_name']  ?? null,
            $p['middle_name'] ?? null,
        ])));
    }

    public function handle(array $guardianData, array $studentData, $guardianType): Student
    {
        return DB::transaction(function () use ($guardianData, $studentData, $guardianType) {
            // 1) Родитель/представитель
            $guardianUser = User::create([
                'last_name'   => $guardianData['last_name'],
                'first_name'  => $guardianData['first_name'],
                'middle_name' => $guardianData['middle_name'] ?? null,
                'email'       => $guardianData['email'],
                'phone'       => $guardianData['phone'],
                'sex'         => $guardianData['sex'], // male|female
                'pin'         => $guardianData['pin'],
                'citizenship' => $guardianData['citizenship'],
                'address'     => $guardianData['address'],
                // временный пароль родителю (если логин нужен в будущем)
                'password'    => Hash::make(Str::password(10)),
            ]);

            // Назначаем роль в зависимости от guardianType
            // parent -> роль 'parent'
            // representative -> роль 'guardian'
            $role = $guardianType === 'parent' ? 'parent' : 'guardian';
            $guardianUser->assignRole($role);
            $guardian = Guardian::create(['user_id' => $guardianUser->id]);

            // 2) Ученик
            $studentUser = User::create([
                'last_name'   => $studentData['last_name'],
                'first_name'  => $studentData['first_name'],
                'middle_name' => $studentData['middle_name'] ?? null,
                'email'       => $studentData['email'] ?? null,
                'phone'       => $studentData['phone'] ?? null,
                'sex'         => $studentData['sex'],
                'pin'         => $studentData['pin'],
                'citizenship' => $studentData['citizenship'],
                'address'     => null,
                'password'    => Hash::make($studentData['password']), // логин при необходимости через родителя/школьный аккаунт
            ]);
            $studentUser->assignRole('student');

            $student = Student::create([
                'user_id'      => $studentUser->id,
                'birth_date'   => $studentData['birth_date'],
                'level_id'        => $studentData['level_id'],
                'class_letter' => $studentData['class_letter'] ?? null,
            ]);

            // 3) Связь
            $student->guardians()->attach($guardian->id);

            // 4) Аудит
            activity()
                ->causedBy(auth()->user() ?? $guardianUser)
                ->performedOn($studentUser)
                ->withProperties([
                    'guardian_user_id' => $guardianUser->id,
                    'student_user_id'  => $studentUser->id,
                ])
                ->log('student_registered');

            return $student;
        });
    }
}
