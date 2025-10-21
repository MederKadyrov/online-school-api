<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Получить профиль текущего учителя
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('teacher')) {
            abort(403, 'Access denied');
        }

        $teacher = $user->teacher()->with('subjects')->first();

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            'subjects' => $teacher && $teacher->subjects ? $teacher->subjects->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ];
            }) : [],
        ]);
    }

    /**
     * Изменить пароль учителя
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('teacher')) {
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
