<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
   public function index()
    {
        // Pastikan pengguna memiliki izin untuk mengelola pengguna
        Gate::authorize('manage-users'); // Asumsi izin 'manage-users' telah dibuat

        // Ambil semua pengguna, bisa juga ditambahkan paginasi
        $users = User::orderBy('created_at', 'desc')->paginate(10); // Contoh paginasi

        return view('admin.users.index', compact('users'));
    }

        public function edit(User $user)
    {
        Gate::authorize('manage-users');

        // Ambil semua peran yang tersedia jika menggunakan Spatie
        $roles = ['admin', 'app_user', 'executive_user'];

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('manage-users');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|in:admin,app_user,executive_user', // Validasi peran dari daftar yang diperbolehkan
            'hierarchy_level_code' => 'nullable|string|max:255',
            'dashboard_route_name' => 'nullable|string|max:255',
            'is_approved' => 'boolean', // Validasi boolean untuk checkbox
            'password' => 'nullable|string|min:8|confirmed', // Untuk reset password
        ]);

        $userData = $request->only([
            'name', 'email', 'hierarchy_level_code', 'dashboard_route_name', 'is_approved', 'role' // Tambahkan 'role' di sini
        ]);

        // Tangani pembaruan password jika ada
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        // Perbarui data pengguna
        $user->update($userData);
        
        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil diperbarui!');
    }

    public function destroy(User $user)
    {
        Gate::authorize('manage-users'); // Pengguna harus punya izin untuk menghapus

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus!');
    }
}
