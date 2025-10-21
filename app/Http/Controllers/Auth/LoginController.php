<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'pin'=>'required|string',
            'password'=>'required|string'
        ]);

        $user = User::where('pin', $validated['pin'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message'=>'Invalid credentials'], 422);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'pin'      => $user->pin,
                'role'     => $user->getRoleNames()->first(),
                'username' => $user->name,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'pin'      => $user->pin,
            'role'     => $user->getRoleNames()->first(),
            'username' => $user->name,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message'=>'ok']);
    }
}
