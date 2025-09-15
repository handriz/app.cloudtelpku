<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission; 
use App\Models\Role;
use Illuminate\Support\Facades\DB; // Impor DB Facade

use Illuminate\Support\Facades\Schema; 

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- PENTING: Nonaktifkan Foreign Key Checks Sementara ---
        Schema::disableForeignKeyConstraints();

        // Hapus semua data yang ada di tabel permissions dan role_has_permissions
        DB::table('permissions')->truncate();
        DB::table('role_permissions')->truncate();


        // --- PENTING: Aktifkan Kembali Foreign Key Checks ---
        Schema::enableForeignKeyConstraints();

            $permissions = [
            // Dashboard
            ['name' => 'access-admin-dashboard', 'description' => 'Acces dashboard Admin '],
            ['name' => 'access-tl_user-dashboard', 'description' => 'Acces dashboard TL User '],
            ['name' => 'access-app_user-dashboard', 'description' => 'Acces dashboard App User '],
            ['name' => 'access-executive-dashboard', 'description' => 'Acces dashboard Eksekutif '],

            // Manajemen Pengguna
            ['name' => 'manage-users', 'description' => 'Mengelola modul pengguna'],
            ['name' => 'view-user-list', 'description' => 'Melihat daftar pengguna'],
            ['name' => 'create-user', 'description' => 'Membuat pengguna baru'],
            ['name' => 'edit-user', 'description' => 'Mengedit pengguna'],
            ['name' => 'delete-user', 'description' => 'Menghapus pengguna'],

            // Manajemen Menu
            ['name' => 'manage-menus', 'description' => 'Mengelola modul menu'],
            ['name' => 'create-menu-item', 'description' => 'Membuat item menu baru'],
            ['name' => 'edit-menu-item', 'description' => 'Mengedit item menu'],
            ['name' => 'delete-menu-item', 'description' => 'Menghapus item menu'],

            // Manajemen Izin
            ['name' => 'manage-permissions', 'description' => 'Mengelola modul izin'],
            ['name' => 'create-permission', 'description' => 'Membuat izin baru'],
            ['name' => 'edit-permission', 'description' => 'Mengedit izin'],
            ['name' => 'delete-permission', 'description' => 'Menghapus izin'],

            // Manajemen Izin
            ['name' => 'manage-hierarchy-levels', 'description' => 'Mengelola modul level hirarki (induk)'],
            ['name' => 'view-hierarchy-level-list', 'description' => 'Melihat daftar level hirarki'],
            ['name' => 'create-hierarchy-level', 'description' => 'Membuat level hirarki baru'],
            ['name' => 'edit-hierarchy-level', 'description' => 'Mengedit level hirarki'],
            ['name' => 'delete-hierarchy-level', 'description' => 'Menghapus level hirarki'],

            // Manajemen Data
            ['name' => 'view-dashboard-master-data', 'description' => 'Melihat dashboard rekapan Master Data'],
            ['name' => 'manage-master-data', 'description' => 'Mengelola modul Master Data induk'],
            ['name' => 'view-master-data', 'description' => 'Melihat Data Pelanggan'],
            ['name' => 'view-queue-monitor', 'description' => 'Monitoring Antrian Upload'],
            ['name' => 'upload-master_data', 'description' => 'Mengupload Master Data Pelanggan (Excel)'],
            ['name' => 'edit-master-data', 'description' => 'Mengedit satu data Master Data Pelanggan'],
            ['name' => 'delete-master-data', 'description' => 'Menghapus data Master Data Pelanggan'],
                
            // Manajemen Worker
            ['name' => 'manage-workers', 'description' => 'Mengelola dan memantau queue workers (Supervisor)'],
        ];

        // Update atau buat izin, dan hapus izin yang tidak lagi didefinisikan di sini
        $currentPermissionNames = collect($permissions)->pluck('name')->toArray();
        foreach ($permissions as $permissionData) {
            Permission::updateOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }
        Permission::whereNotIn('name', $currentPermissionNames)->delete();

        // --- Kaitkan Izin dengan Peran ---

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $tlUserRole = Role::where('name', 'tl_user')->firstOrFail();
        $appUserRole = Role::where('name', 'app_user')->firstOrFail();
        $executiveUserRole = Role::where('name', 'executive_user')->firstOrFail();

        // Pastikan peran ditemukan
        if (!$adminRole) { throw new \Exception('Admin Role not found! Run RoleSeeder first.'); }
        if (!$tlUserRole) { throw new \Exception('TL User Role not found! Run RoleSeeder first.'); }
        if (!$appUserRole) { throw new \Exception('App User Role not found! Run RoleSeeder first.'); }
        if (!$executiveUserRole) { throw new \Exception('Executive User Role not found!'); }

        // --- Izin untuk Admin (akses semua) ---
        // Admin akan memiliki semua izin yang ada
        $allPermissionIds = Permission::pluck('id');
        $adminRole->permissions()->sync($allPermissionIds);

        // --- Izin untuk App User ---
        $tlUserPermissions = Permission::whereIn('name', [
            'access-tl_user-dashboard',
            'manage-users',
            'view-user-list',
            'create-user',
            'edit-user'
        ])->pluck('id');
        $tlUserRole->permissions()->sync($tlUserPermissions);

        // --- Izin untuk App User ---
        $appUserPermissions = Permission::whereIn('name', [
            'access-app_user-dashboard'
        ])->pluck('id');
        $appUserRole->permissions()->sync($appUserPermissions);

        // --- Izin untuk Executive User ---
        $executiveUserPermissions = Permission::whereIn('name', [
            'access-executive-dashboard',
            'view-user-list',
            'view-master-data',
            'view-dashboard-master-data'
        ])->pluck('id');
        $executiveUserRole->permissions()->sync($executiveUserPermissions);
    }
}
