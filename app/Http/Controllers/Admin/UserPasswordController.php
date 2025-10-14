<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserPasswordController extends Controller
{
    /**
     * Получить список пользователей для управления паролями
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->select('id', 'pin', 'first_name', 'last_name', 'middle_name', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name');

        // Поиск по ФИО или PIN
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('pin', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        return $users->through(function($user) {
            return [
                'id' => $user->id,
                'pin' => $user->pin,
                'full_name' => trim(implode(' ', array_filter([
                    $user->last_name,
                    $user->first_name,
                    $user->middle_name
                ]))),
                'email' => $user->email,
            ];
        });
    }

    /**
     * Сбросить пароль пользователя и выдать временный
     */
    public function resetPassword(Request $request, User $user)
    {
        // Генерируем временный пароль
        $tempPassword = Str::random(10);

        $user->update([
            'password' => Hash::make($tempPassword),
        ]);

        return response()->json([
            'message' => 'Пароль успешно сброшен',
            'temp_password' => $tempPassword,
            'user' => [
                'id' => $user->id,
                'pin' => $user->pin,
                'full_name' => trim(implode(' ', array_filter([
                    $user->last_name,
                    $user->first_name,
                    $user->middle_name
                ]))),
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Установить новый пароль пользователю
     */
    public function setPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Пароль успешно установлен',
        ]);
    }
}
