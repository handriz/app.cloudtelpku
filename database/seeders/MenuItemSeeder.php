<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Support\Facades\Schema; // Pastikan Schema diimpor
use Illuminate\Support\Facades\DB;

class MenuItemSeeder extends Seeder
{
    /**
     * Jalankan seed database.
     */
    public function run(): void
    {
        // --- PENTING: Nonaktifkan Foreign Key Checks Sementara ---
        Schema::disableForeignKeyConstraints();

        // Hapus semua menu yang ada untuk menghindari duplikasi saat seeding ulang
        DB::table('role_menu')->truncate();
        DB::table('menu_items')->truncate();

        // --- PENTING: Aktifkan Kembali Foreign Key Checks ---
        Schema::enableForeignKeyConstraints();

        $adminRole = Role::where('name', 'admin')->first();
        $appUserRole = Role::where('name', 'app_user')->first();
        $executiveUserRole = Role::where('name', 'executive_user')->first();

        // --- Menu Utama Admin ---
        $dashboard = MenuItem::create([
            'name' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'route_name' => 'admin.dashboard',
            'permission_name' => 'view-dashboard',
            'order' => 10,
            'is_active' => true,
            
        ]);

        // PERUBAHAN DI SINI: route_name SET KE NULL UNTUK MENU INDUK
        $manajemenPengguna = MenuItem::create([
            'name' => 'Manajemen Pengguna',
            'icon' => 'fas fa-users',
            'route_name' => null, // <-- INI HARUS NULL JIKA HANYA SEBAGAI INDUK/HEADER MENU
            'permission_name' => 'manage-users',
            'order' => 20,
            'is_active' => true,
        ]);

        // Sub-menu untuk Manajemen Pengguna
        // Ini adalah link sebenarnya ke admin.users.index
        $usersIndex = MenuItem::create([
            'parent_id' => $manajemenPengguna->id,
            'name' => 'Daftar Pengguna',
            'icon' => 'fas fa-user-friends',
            'route_name' => 'admin.users.index', // Ini yang seharusnya punya rute
            'permission_name' => 'view-user-list',
            'order' => 10,
            'is_active' => true,
        ]);
        $usersCreate = MenuItem::create([
            'name' => 'Tambah Pengguna',
            'route_name' => 'admin.users.create', // Pastikan rute ini ada
            'icon' => 'fas fa-user-plus',
            'permission_name' => 'create-user', // Gate yang diperlukan
            'parent_id' => $manajemenPengguna->id,
            'order' => 20,
            'is_active' => true,
        ]);

        $pengaturan  = MenuItem::create([
            'name' => 'Pengaturan',
            'icon' => 'fas fa-cogs',
            'route_name' => null, // Ini juga induk
            'permission_name' => 'manage-settings',
            'order' => 30,
            'is_active' => true,
        ]);

        // Sub-menu untuk Pengaturan
       $menuIndex = MenuItem::create([
            'parent_id' => $pengaturan->id,
            'name' => 'Manajemen Menu',
            'icon' => 'fas fa-list',
            'route_name' => 'admin.menu.index',
            'permission_name' => 'manage-menus  ',
            'order' => 10,
            'is_active' => true,
        ]);
       $permissionsIndex = MenuItem::create([
            'parent_id' => $pengaturan->id,
            'name' => 'Manajemen Izin',
            'icon' => 'fas fa-key',
            'route_name' => 'admin.permissions.index',
            'permission_name' => 'manage-permissions',
            'order' => 20,
            'is_active' => true,
        ]);
        
        // --- Asosiasi Menu dengan Peran (Melalui Tabel Pivot role_menu) ---
        // Ini menghubungkan ID Role dengan ID MenuItem

        if ($adminRole) {
            $adminRole->menuItems()->attach([
                $dashboard->id,
                $manajemenPengguna->id, // Akses ke menu induk agar sub-menu terlihat
                $usersIndex->id,
                $usersCreate->id,
                $pengaturan->id, // Akses ke menu induk agar sub-menu terlihat
                $permissionsIndex->id,
                $menuIndex->id,
            ]);
        }

        if ($appUserRole) {
            $appUserRole->menuItems()->attach([
                $dashboard->id,
                $manajemenPengguna->id, // Akses ke menu induk
                $usersIndex->id, // Hanya izinkan view-user-list
            ]);
        }

        if ($executiveUserRole) {
            $executiveUserRole->menuItems()->attach([
                $dashboard->id,
                $manajemenPengguna->id, // Akses ke menu induk
                $usersIndex->id,
                $pengaturan->id, // Akses ke menu induk
                $permissionsIndex->id,
            ]);
        }
    }
}