<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Получить профиль текущего студента
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('student')) {
            abort(403, 'Access denied');
        }

        $student = $user->student()->with(['group.level', 'studentDocument'])->first();

        // Преобразуем документы студента в массив
        $documents = [];
        if ($student && $student->studentDocument) {
            $doc = $student->studentDocument;

            if ($doc->guardian_application_path) {
                $documents[] = [
                    'id' => 1,
                    'name' => 'Заявление опекуна',
                    'type' => 'Заявление',
                    'file_url' => url('storage/' . $doc->guardian_application_path),
                    'uploaded_at' => $doc->created_at ? $doc->created_at->toISOString() : null,
                ];
            }

            if ($doc->birth_certificate_path) {
                $documents[] = [
                    'id' => 2,
                    'name' => 'Свидетельство о рождении',
                    'type' => 'Свидетельство',
                    'file_url' => url('storage/' . $doc->birth_certificate_path),
                    'uploaded_at' => $doc->created_at ? $doc->created_at->toISOString() : null,
                ];
            }

            if ($doc->student_pin_doc_path) {
                $documents[] = [
                    'id' => 3,
                    'name' => 'Документ с ПИН студента',
                    'type' => 'Удостоверение',
                    'file_url' => url('storage/' . $doc->student_pin_doc_path),
                    'uploaded_at' => $doc->created_at ? $doc->created_at->toISOString() : null,
                ];
            }

            if ($doc->guardian_passport_path) {
                $documents[] = [
                    'id' => 4,
                    'name' => 'Паспорт опекуна',
                    'type' => 'Паспорт',
                    'file_url' => url('storage/' . $doc->guardian_passport_path),
                    'uploaded_at' => $doc->created_at ? $doc->created_at->toISOString() : null,
                ];
            }

            if ($doc->medical_certificate_path) {
                $documents[] = [
                    'id' => 5,
                    'name' => 'Медицинская справка',
                    'type' => 'Справка',
                    'file_url' => url('storage/' . $doc->medical_certificate_path),
                    'uploaded_at' => $doc->created_at ? $doc->created_at->toISOString() : null,
                ];
            }
        }

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'pin' => $user->pin,
            'gender' => $user->sex, // в БД поле называется sex
            'birth_date' => $student ? $student->birth_date : null,
            'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            'documents' => $documents,
            'group' => $student && $student->group ? [
                'id' => $student->group->id,
                'class_letter' => $student->group->class_letter,
                'display_name' => $student->group->display_name,
                'level' => $student->group->level ? [
                    'id' => $student->group->level->id,
                    'number' => $student->group->level->number,
                ] : null,
            ] : null,
        ]);
    }

    /**
     * Изменить пароль студента
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('student')) {
            abort(403, 'Access denied');
        }

        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Проверяем текущий пароль
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Неверный текущий пароль'
            ], 422);
        }

        // Обновляем пароль
        $user->password = Hash::make($data['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Пароль успешно изменен'
        ]);
    }
}
