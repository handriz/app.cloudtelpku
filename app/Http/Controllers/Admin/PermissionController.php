<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function index(Request $request)
    {

        $roles = Role::all();
        $selectedRole = null;
        $selectedUser = null;

        // --- DATA PERMISSIONS ---
        $allPermissions = Permission::orderBy('name')->get();
        $rolePermissionIds = [];
        
        // --- DATA MENUS (LOGIKA BARU) ---
        $allMenus = MenuItem::orderBy('order')->get();
        $roleMenuIds = [];
        $groupedMenus = collect();

        if ($request->has('role_id')) {
            $selectedRole = Role::find($request->role_id);
            if ($selectedRole) {
                // Ambil ID Permission milik Role
                $rolePermissionIds = $selectedRole->permissions->pluck('id')->toArray();
                
                // Ambil ID Menu milik Role
                $roleMenuIds = $selectedRole->menuItems->pluck('id')->toArray();
            }
        }

        $userMenuIds = [];
        
        // Jika admin memilih User spesifik
        if ($request->has('user_id')) {
            $selectedUser = \App\Models\User::find($request->user_id);
            
            if ($selectedUser) {
                // Cek apakah user ini sudah punya menu khusus?
                if ($selectedUser->hasCustomMenus()) {
                    // Jika YA: Ambil menu khususnya
                    $userMenuIds = $selectedUser->menuItems->pluck('id')->toArray();
                } else {
                    // Jika TIDAK: Tampilkan menu bawaan Role-nya sebagai default (biar admin ga capek centang ulang)
                    $userMenuIds = $selectedUser->role->menuItems->pluck('id')->toArray();
                }
                
                // Set role user tersebut sebagai selectedRole (agar tab permission tetap nyambung)
                $selectedRole = $selectedUser->role;
            }
        } elseif ($request->has('role_id')) {
            // Logika lama (pilih role)
            $selectedRole = Role::find($request->role_id);
        }

        // Grouping Permissions (Logic Lama)
        $groupedPermissions = $allPermissions->groupBy(function ($permission) {
            $parts = explode('-', $permission->name);
            return isset($parts[0]) ? ucfirst($parts[0]) : 'Lainnya';
        })->sortKeys();

        // Grouping Menus (Logic Baru: Parent-Child)
        $groupedMenus = $allMenus->whereNull('parent_id')->map(function ($parent) use ($allMenus) {
            $parent->children = $allMenus->where('parent_id', $parent->id)->sortBy('order');
            return $parent;
        });

        // Gabungkan semua data ke view
        $viewData = compact(
            'roles', 
            'selectedRole', 
            'selectedUser',
            'groupedPermissions', 
            'rolePermissionIds',
            'groupedMenus', // Data baru
            'roleMenuIds',   // Data baru
            'userMenuIds'
        );

        if ($request->has('is_ajax')) {
            return view('admin.permissions.partials.index_content', $viewData);
        }

        return view('admin.permissions.index', $viewData);
    }

    /**
     * MENAMPILKAN FORM TAMBAH IZIN (Ini yang menyebabkan Error 500 sebelumnya)
     */
    public function create(Request $request)
    {
        // Pastikan Anda sudah menyimpan file view create di folder:
        // resources/views/admin/permissions/partials/create_content.blade.php
        
        if ($request->has('is_ajax')) {
            return view('admin.permissions.partials.create_content');
        }

        // Fallback jika diakses tanpa AJAX (opsional)
        return view('admin.permissions.create'); 
    }

    /**
     * MENYIMPAN IZIN BARU KE DATABASE
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            Permission::create([
                'name' => $request->name,
                'description' => $request->description,
                'guard_name' => 'web', // Default guard
            ]);

            // Return JSON agar ditangkap oleh tab-manager.js (handleModalFormSubmit)
            return response()->json([
                'success' => true,
                'message' => 'Izin baru berhasil ditambahkan!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat izin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateUserMenus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'menu_ids' => 'array',
            'menu_ids.*' => 'exists:menu_items,id',
        ]);

        $user = \App\Models\User::findOrFail($request->user_id);
        
        // Simpan ke tabel pivot user_menu
        $user->menuItems()->sync($request->input('menu_ids', []));

        // Bersihkan cache
        Cache::flush();

        return redirect()
            ->route('admin.permissions.index', ['user_id' => $user->id]) // Redirect balik ke user tsb
            ->with('success', "Menu khusus untuk User '{$user->name}' berhasil disimpan. User ini sekarang menggunakan pengaturan menu PRIBADI (mengabaikan Role).");
    }

    // --- METHOD BARU: Reset Menu User (Kembali ke Role) ---
    public function resetUserMenus(Request $request)
    {
        $user = \App\Models\User::findOrFail($request->user_id);
        $user->menuItems()->detach(); // Hapus semua menu khusus
        Cache::flush();

        return redirect()
            ->route('admin.permissions.index', ['user_id' => $user->id])
            ->with('success', "Menu User '{$user->name}' dikembalikan ke pengaturan default Role '{$user->role->name}'.");
    }
    
    public function updateRolePermissions(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($request->role_id);
        $inputPermissions = $request->input('permissions', []);
        if ($role->name === 'admin') {
            // Cari ID permission 'manage-permissions' (sesuaikan dengan nama di seeder Anda)
            $criticalPermission = Permission::where('name', 'manage-permissions')->first();
            
            if ($criticalPermission) {
                // Pastikan ID ini SELALU ADA di array, tidak peduli apa yang dikirim form
                if (!in_array($criticalPermission->id, $inputPermissions)) {
                    $inputPermissions[] = $criticalPermission->id;
                }
            }
        }
        // Gunakan array yang sudah diproteksi ($inputPermissions) bukan raw request
        $role->permissions()->sync($inputPermissions);

        Cache::forget('permissions.all');

        return redirect()
            ->route('admin.permissions.index', ['role_id' => $role->id])
            ->with('success', "Izin untuk peran '{$role->name}' berhasil diperbarui.");
    }

    public function updateRoleMenus(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'menu_ids' => 'array',
            'menu_ids.*' => 'exists:menu_items,id',
        ]);

        $role = Role::findOrFail($request->role_id);
        
        // HANYA update menu (Tampilan Sidebar)
        $role->menuItems()->sync($request->input('menu_ids', []));

        Cache::flush(); // Bersihkan cache sidebar jika ada

        // Redirect agar tetap di halaman ini
        return redirect()
            ->route('admin.permissions.index', ['role_id' => $role->id])
            ->with('success', "Visibilitas Menu Sidebar untuk peran '{$role->name}' berhasil diperbarui.");
    }
}