<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Шаг 1: Отправка кода на email по PIN
     */
    public function sendResetCode(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string|size:14',
        ]);

        $user = User::where('pin', $validated['pin'])->first();

        if (!$user || !$user->email) {
            return response()->json([
                'message' => 'Пользователь с таким PIN не найден или email не указан. Обратитесь к администратору.'
            ], 404);
        }

        // Генерируем 6-значный код
        $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(60);

        // Сохраняем в БД (expires через 15 минут)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['pin' => $user->pin],
            [
                'email' => $user->email,
                'token' => Hash::make($code),
                'created_at' => now(),
                'expires_at' => now()->addMinutes(15),
            ]
        );

        // Отправляем email с кодом
        try {
            Mail::raw(
                "Код для сброса пароля: {$code}\n\nКод действителен 15 минут.\n\nЕсли вы не запрашивали сброс пароля, проигнорируйте это письмо.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Восстановление пароля');
                }
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка отправки email. Попробуйте позже или обратитесь к администратору.'
            ], 500);
        }

        return response()->json([
            'message' => 'Код отправлен на email',
            'email' => $this->maskEmail($user->email),
        ]);
    }

    /**
     * Шаг 2: Проверка кода и сброс пароля
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string|size:14',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('pin', $validated['pin'])->first();

        if (!$user) {
            return response()->json(['message' => 'Пользователь не найден'], 404);
        }

        // Проверяем токен из БД
        $resetToken = DB::table('password_reset_tokens')
            ->where('pin', $user->pin)
            ->first();

        if (!$resetToken) {
            return response()->json(['message' => 'Код не найден. Запросите новый код.'], 404);
        }

        // Проверяем срок действия
        if (now()->gt($resetToken->expires_at)) {
            DB::table('password_reset_tokens')->where('pin', $user->pin)->delete();
            return response()->json(['message' => 'Код истек. Запросите новый код.'], 422);
        }

        // Проверяем код
        if (!Hash::check($validated['code'], $resetToken->token)) {
            return response()->json(['message' => 'Неверный код'], 422);
        }

        // Обновляем пароль
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Удаляем токен
        DB::table('password_reset_tokens')->where('pin', $user->pin)->delete();

        return response()->json([
            'message' => 'Пароль успешно изменен',
        ]);
    }

    /**
     * Маскировка email для безопасности
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;

        $name = $parts[0];
        $domain = $parts[1];

        if (strlen($name) <= 2) {
            $maskedName = $name[0] . str_repeat('*', strlen($name) - 1);
        } else {
            $maskedName = $name[0] . str_repeat('*', strlen($name) - 2) . $name[strlen($name) - 1];
        }

        return $maskedName . '@' . $domain;
    }
}
