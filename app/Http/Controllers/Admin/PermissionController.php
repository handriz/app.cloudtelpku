<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PermissionController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        $permissions = Permission::all();

        // Dapatkan izin yang terkait dengan setiap peran
        $rolePermissions = [];
        foreach ($roles as $role) {
            $rolePermissions[$role->name] = $role->permissions->pluck('id')->toArray();
        }

        return view('admin.permissions.index', compact('permissions', 'roles', 'rolePermissions'));
    }

    public function create()
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name', // Nama izin harus unik
            'description' => 'nullable|string|max:500',
        ]);

        Permission::create($request->all());

        return redirect()->route('admin.permissions.index')->with('success', 'Izin berhasil ditambahkan!');
    }

    public function edit(Permission $permission)
    {
        return view('admin.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:500',
        ]);

        $permission->update($request->all());

        return redirect()->route('admin.permissions.index')->with('success', 'Izin berhasil diperbarui!');
    }

    public function destroy(Permission $permission)
    {
        try {
            $permission->delete();
            return redirect()->route('admin.permissions.index')->with('success', 'Izin berhasil dihapus!');
        } catch (\Exception $e) {
            return redirect()->route('admin.permissions.index')->with('error', 'Gagal menghapus izin: ' . $e->getMessage());
        }
    }

    /**
     * Simpan pembaruan izin peran.
     */
    public function updateRolePermissions(Request $request)
    {
        $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'array',
            'permissions.*.*' => 'in:0,1', // Memastikan nilai adalah boolean
        ]);

      $inputPermissions = $request->input('permissions', []);

// Iterasi melalui daftar SEMUA PERAN yang kita ketahui,
        // BUKAN hanya peran yang datanya dikirim oleh form.
        $allRoles = Role::all();
        foreach ($allRoles as $role) {
            // Dapatkan ID izin yang dicentang untuk peran saat ini dari input request
            // Jika peran tidak ada dalam input, asumsikan tidak ada izin yang dicentang (array kosong)
            $selectedPermissionIds = collect($inputPermissions[$role->name] ?? [])
                                     ->filter(fn($value) => $value == 1) // Filter hanya yang nilainya '1' (dicentang)
                                     ->keys() // Ambil kuncinya, yang merupakan ID izin
                                     ->map(fn($key) => (int) $key) // Pastikan ID adalah integer
                                     ->toArray();

            // Menggunakan metode sync() untuk memperbarui tabel pivot role_permissions
            // sync() akan:
            // 1. Menghapus semua izin yang tidak ada di $selectedPermissionIds untuk peran ini.
            // 2. Menambahkan semua izin di $selectedPermissionIds yang belum ada untuk peran ini.
            // 3. Membiarkan izin yang sudah ada di kedua sisi.
            $role->permissions()->sync($selectedPermissionIds);
            
        }

        return redirect()->route('admin.permissions.index')->with('success', 'Izin peran berhasil diperbarui!');
    }
}
