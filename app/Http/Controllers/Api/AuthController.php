<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\UserDevice;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_id' => 'required|string',
            'model' => 'nullable|string',
            'app_version' => 'nullable|string',
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

        // 5. Cek apakah Perangkat ini DIBLOKIR oleh Admin?
        // Kita cari device berdasarkan ID uniknya
        $device = UserDevice::where('device_id', $request->device_id)->first();

        if ($device && $device->is_blocked) {
            return response()->json([
                'status' => 'error',
                'message' => 'PERANGKAT DIBLOKIR. Hubungi Admin untuk membuka akses.',
                'code' => 'DEVICE_BLOCKED'
            ], 403);
        }

        // 6. Catat/Update Data Perangkat (Agar muncul di Admin Panel)
        // Jika user pindah HP, device_id baru akan tercatat
        UserDevice::updateOrCreate(
            [
                'user_id'   => $user->id,
                'device_id' => $request->device_id
            ],
            [
                'model_name'    => $request->model,        // Cth: Samsung A50
                'app_version'   => $request->app_version,  // Cth: 1.0.2
                'last_ip'       => $request->ip(),
                'last_login_at' => now(),
                // 'is_blocked' => false // Jangan di-unblock otomatis, biarkan Admin yang atur
            ]
        );

        // 7. Jika semua lolos, buat token Sanctum
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

    /**
     * Logout API (Hapus Token)
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }
}
