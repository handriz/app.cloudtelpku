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

        // --- Definisi Item Menu ---
        $dashboard = MenuItem::create([
            'name' => 'Dashboard',
            'route_name' => 'dashboard', 
            'icon' => 'fas fa-tachometer-alt',
            'permission_name' => 'view-dashboard',
            'parent_id' => null,
            'order' => 10,
            'is_active' => true,
        ]);

        $manajemenPengguna = MenuItem::create([
            'name' => 'Manajemen Pengguna',
            'route_name' => null, 
            'icon' => 'fas fa-users',
            'permission_name' => 'manage-users', 
            'parent_id' => null,
            'order' => 20,
            'is_active' => true,
        ]);

        $usersIndex = MenuItem::create([
            'name' => 'Daftar Pengguna',
            'route_name' => 'admin.users.index', 
            'icon' => 'fas fa-user-friends',
            'permission_name' => 'view-user-list', 
            'parent_id' => $manajemenPengguna->id,
            'order' => 21,
            'is_active' => true,
        ]);
        $usersCreate = MenuItem::create([
            'name' => 'Tambah Pengguna',
            'route_name' => 'admin.users.create', 
            'icon' => 'fas fa-user-plus',
            'permission_name' => 'create-user', 
            'parent_id' => $manajemenPengguna->id,
            'order' => 22,
            'is_active' => true,
        ]);

        $manajemenData = MenuItem::create([
            'name' => 'Manajemen Data',
            'route_name' => null, // Ini adalah menu induk, tidak punya rute langsung
            'icon' => 'fas fa-database', // Icon untuk manajemen data
            'permission_name' => 'manage-master-data', // Izin induk
            'parent_id' => null,
            'order' => 25, // Urutan setelah Manajemen Pengguna
            'is_active' => true,
        ]);

        $dataDashboard = MenuItem::create([
            'name' => 'Dashboard Rekap Data',
            'route_name' => 'admin.manajemen_data.dashboard',
            'icon' => 'fas fa-chart-bar', // Icon untuk dashboard rekapan
            'permission_name' => 'view-master-data-dashboard',
            'parent_id' => $manajemenData->id,
            'order' => 251,
            'is_active' => true,
        ]);

        $masterDataPelanggan = MenuItem::create([
            'name' => 'Master Data Pelanggan',
            'route_name' => 'admin.manajemen_data.index',
            'icon' => 'fas fa-users-cog', // Icon untuk master data pelanggan
            'permission_name' => 'view-master-data-pelanggan',
            'parent_id' => $manajemenData->id,
            'order' => 252,
            'is_active' => true,
        ]);

        $uploadDataPelanggan = MenuItem::create([
            'name' => 'Upload Data Pelanggan',
            'route_name' => 'admin.manajemen_data.upload.form',
            'icon' => 'fas fa-upload', // Icon untuk upload data
            'permission_name' => 'upload-master-data-pelanggan',
            'parent_id' => $manajemenData->id,
            'order' => 253,
            'is_active' => true,
        ]);

        $pengaturan = MenuItem::create([
            'name' => 'Pengaturan',
            'route_name' => null, 
            'icon' => 'fas fa-cogs',
            'permission_name' => 'manage-settings', 
            'parent_id' => null,
            'order' => 30,
            'is_active' => true,
        ]);
        
        $permissionsIndex = MenuItem::create([
            'name' => 'Manajemen Izin',
            'route_name' => 'admin.permissions.index', 
            'icon' => 'fas fa-user-lock',
            'permission_name' => 'manage-permissions', 
            'parent_id' => $pengaturan->id,
            'order' => 31,
            'is_active' => true,
        ]);
        $menuIndex = MenuItem::create([
            'name' => 'Manajemen Menu',
            'route_name' => 'admin.menu.index', 
            'icon' => 'fas fa-bars',
            'permission_name' => 'manage-menus', 
            'parent_id' => $pengaturan->id,
            'order' => 32,
            'is_active' => true,
        ]);

        // --- Item Menu BARU untuk Hirarki ---
        $hierarchyIndex = MenuItem::create([
            'name' => 'Manajemen Hirarki',
            'route_name' => 'admin.hierarchies.index', 
            'icon' => 'fas fa-project-diagram', 
            'permission_name' => 'manage-hierarchy-levels', // Izin untuk menu ini
            'parent_id' => $pengaturan->id, // Letakkan di bawah menu "Pengaturan"
            'order' => 33, 
            'is_active' => true,
        ]);

        $supervisorWorkers = MenuItem::create([
            'name' => 'Manajemen Queue Workers',
            'route_name' => 'admin.supervisor.index',
            'icon' => 'fas fa-cogs', // Atau ikon lain yang sesuai
            'permission_name' => 'manage-workers',
            'parent_id' => $pengaturan->id, // Ditempatkan di bawah Pengaturan
            'order' => 34, 
            'is_active' => true,
        ]);
        // --- Akhir Item Menu BARU ---


        // --- Asosiasi Menu dengan Peran (Melalui Tabel Pivot role_menu) ---
        if ($adminRole) {
            $adminRole->menuItems()->attach([
                $dashboard->id,
                $manajemenPengguna->id, 
                $usersIndex->id, $usersCreate->id,
                $manajemenData->id, 
                $dataDashboard->id,
                $pengaturan->id, 
                $permissionsIndex->id, $menuIndex->id,
                $hierarchyIndex->id,
                $supervisorWorkers->id,
            ]);
        }

        if ($appUserRole) {
            $appUserRole->menuItems()->attach([
                $dashboard->id,
                $manajemenData->id, 
                $manajemenPengguna->id,
                $dataDashboard->id,
                $usersIndex->id,
                $masterDataPelanggan->id,
            ]);
        }

        if ($executiveUserRole) {
            $executiveUserRole->menuItems()->attach([
                $dashboard->id,
                $manajemenPengguna->id,
                $usersIndex->id,
                $pengaturan->id,
                $permissionsIndex->id, 
                $hierarchyIndex->id, // Kaitkan menu baru dengan peran executive
            ]);
        }
    }
}