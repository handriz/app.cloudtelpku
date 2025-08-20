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

        // --- 1. Buat Izin (Permissions) ---
        // $permissionsData = [
        //     ['name' => 'access-admin-dashboard', 'description' => 'Akses ke dashboard admin.'],
        //     ['name' => 'manage-users', 'description' => 'Melihat, membuat, mengedit, dan menghapus pengguna.'],
        //     ['name' => 'approve-users', 'description' => 'Menyetujui pendaftaran pengguna baru.'],
        //     ['name' => 'view-users', 'description' => 'Melihat daftar pengguna.'],
        //     ['name' => 'create-users', 'description' => 'Membuat pengguna baru.'],
        //     ['name' => 'edit-users', 'description' => 'Mengedit data pengguna.'],
        //     ['name' => 'delete-users', 'description' => 'Menghapus pengguna.'],

        //     ['name' => 'manage-settings', 'description' => 'Mengelola pengaturan umum aplikasi.'],
        //     ['name' => 'manage-menu', 'description' => 'Mengelola item menu navigasi.'],
        //     ['name' => 'manage-permissions', 'description' => 'Mengelola izin dan penugasan peran.'], // Izin baru untuk manajemen izin

        //     ['name' => 'access-app_user-dashboard', 'description' => 'Akses ke dashboard pengguna aplikasi.'],
        //     ['name' => 'view-my-reports', 'description' => 'Melihat laporan pribadi.'],
        //     ['name' => 'access-executive-dashboard', 'description' => 'Akses ke dashboard eksekutif.'],
        // ];

        // Buat izin
        $viewDashboard = Permission::firstOrCreate(['name' => 'view-dashboard']);
        $manageUsers = Permission::firstOrCreate(['name' => 'manage-users']);
        $viewUserList = Permission::firstOrCreate(['name' => 'view-user-list']);
        $createUser = Permission::firstOrCreate(['name' => 'create-user']);
        $editUser = Permission::firstOrCreate(['name' => 'edit-user']); // Contoh izin tambahan
        $deleteUser = Permission::firstOrCreate(['name' => 'delete-user']); // Contoh izin tambahan
        $manageSettings = Permission::firstOrCreate(['name' => 'manage-settings']);
        $managePermissions = Permission::firstOrCreate(['name' => 'manage-permissions']);
        $manageMenus = Permission::firstOrCreate(['name' => 'manage-menus']);

        // Dapatkan peran
        $adminRole = Role::where('name', 'admin')->first();
        $appUserRole = Role::where('name', 'app_user')->first();
        $executiveUserRole = Role::where('name', 'executive_user')->first();

        // Kaitkan izin ke peran
        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching([
                $viewDashboard->id,
                $manageUsers->id, $viewUserList->id, $createUser->id, $editUser->id, $deleteUser->id,
                $manageSettings->id, $managePermissions->id, $manageMenus->id,
            ]);
        }

        if ($appUserRole) {
            $appUserRole->permissions()->syncWithoutDetaching([
                $viewDashboard->id,
                $viewUserList->id,
            ]);
        }

        if ($executiveUserRole) {
            $executiveUserRole->permissions()->syncWithoutDetaching([
                $viewDashboard->id,
                $viewUserList->id, $editUser->id,
                $manageSettings->id, $managePermissions->id,
            ]);
        }
    }
}
