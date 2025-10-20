<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

        // 2. Coba temukan user berdasarkan email
        $user = User::with('role')->where('email', $request->email)->first();
        
        // 3. Validasi user dan password
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau Password salah.'
            ], 401); // 401 Unauthorized
        }

        // 4. Periksa apakah user diizinkan akses mobile
        if (!$user->mobile_app) { // <-- INI LOGIKA BARUNYA
            return response()->json([
                'message' => 'Akun Anda tidak memiliki izin untuk mengakses aplikasi ini.'
            ], 403); // 403 Forbidden
        }

        // 5. Jika semua lolos, buat token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. Kirim respon sukses beserta token dan data user
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // // 'role' bisa dihapus jika tidak perlu, 
                // // tapi mungkin berguna di Flutter
                // 'role' => $user->role ? $user->role->name : null,
            ]
        ]);
    }
}
