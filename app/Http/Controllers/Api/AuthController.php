<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Coba autentikasi pengguna
        if (!Auth::attempt($credentials)) {
            // Jika gagal, kirim respon error 401
            return response()->json(['message' => 'Username atau Password salah.'], 401);
        }

        // 3. Jika berhasil, buat token
        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Kirim respon sukses beserta token dan data user
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
}
