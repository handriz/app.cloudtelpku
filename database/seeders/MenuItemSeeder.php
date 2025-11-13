<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MenuItemSeeder extends Seeder
{
    /**
     * Jalankan seed database.
     */
    public function run(): void
    {
        Cache::flush();
        
        // --- PENTING: Nonaktifkan Foreign Key Checks Sementara ---
        Schema::disableForeignKeyConstraints();

        // Hapus semua menu yang ada untuk menghindari duplikasi saat seeding ulang
        DB::table('role_menu')->truncate();
        DB::table('menu_items')->truncate();

        // --- PENTING: Aktifkan Kembali Foreign Key Checks ---
        Schema::enableForeignKeyConstraints();

        $adminRole = Role::where('name', 'admin')->first();
        $tlUserRole = Role::where('name', 'team')->first();
        $appUserRole = Role::where('name', 'appuser')->first();
        $executiveUserRole = Role::where('name', 'executive_user')->first();

        // --- Definisi Item Menu ---
        $dashboard = MenuItem::create([
            'name' => 'Dashboard',
            'route_name' => 'dashboard', 
            'icon' => 'fas fa-tachometer-alt',
            'permission_name' => 'access-admin-dashboard',
            'parent_id' => null,
            'order' => 10,
            'is_active' => true,
        ]);

        $manajemenPengguna = MenuItem::create([
            'name' => 'Manajemen User',
            'route_name' => null, 
            'icon' => 'fas fa-users',
            'permission_name' => 'manage-users', 
            'parent_id' => null,
            'order' => 20,
            'is_active' => true,
        ]);

        $usersIndex = MenuItem::create([
            'name' => 'Daftar Pengguna',
            'route_name' => 'manajemen-pengguna.users.index', 
            'icon' => 'fas fa-user-friends',
            'permission_name' => 'manage-user-list', 
            'parent_id' => $manajemenPengguna->id,
            'order' => 21,
            'is_active' => true,
        ]);
        $usersCreate = MenuItem::create([
            'name' => 'Tambah Pengguna',
            'route_name' => 'manajemen-pengguna.users.create', 
            'icon' => 'fas fa-user-plus',
            'permission_name' => 'manage-user-create', 
            'parent_id' => $manajemenPengguna->id,
            'order' => 22,
            'is_active' => true,
        ]);

        $manajemenData = MenuItem::create([
            'name' => 'Bank Data TE',
            'route_name' => null,
            'icon' => 'fas fa-database',
            'permission_name' => 'manage-data-bank', 
            'parent_id' => null,
            'order' => 30,
            'is_active' => true,
        ]);

        $dataPelangganDashboard = MenuItem::create([
            'name' => 'Dashboard Rekap Dil',
            'route_name' => 'admin.manajemen_data.dashboard',
            'icon' => 'fas fa-chart-bar',
            'permission_name' => 'view-dashboard-rekap-dil',
            'parent_id' => $manajemenData->id,
            'order' => 31,
            'is_active' => true,
        ]);

        $DataPelangganIndex = MenuItem::create([
            'name' => 'Dashboard Rekap Mapping',
            'route_name' => '',
            'icon' => 'fas fa-users-cog', // Icon untuk master data pelanggan
            'permission_name' => 'view-master-data',
            'parent_id' => $manajemenData->id,
            'order' => 32,
            'is_active' => true,
        ]);

        $queueImportData = MenuItem::create([
            'name' => 'Monitoring Antrian',
            'route_name' => 'admin.queue.monitor',
            'icon' => 'fas fa-tasks', // Icon untuk upload data
            'permission_name' => 'view-queue-monitor',
            'parent_id' => $manajemenData->id,
            'order' => 33,
            'is_active' => true,
        ]);

        $mapping = MenuItem::create([
            'name' => 'Peta Pelanggan',
            'route_name' => 'team.mapping.index', 
            'icon' => 'fas fa-location-arrow', 
            'permission_name' => 'mapping-pelanggan',
            'parent_id' => $manajemenData->id,
            'order' => 34, 
            'is_active' => true,
        ]);
        $mappingvalidasi = MenuItem::create([
            'name' => 'Validasi Pendataan',
            'route_name' => 'team.mapping_validation.index', 
            'icon' => 'fas fa-map-marker-alt', 
            'permission_name' => 'mapping-validasi',
            'parent_id' => $manajemenData->id,
            'order' => 35, 
            'is_active' => true,
        ]);

        $top2tl = MenuItem::create([
            'name' => 'Pengembangan TO',
            'route_name' => 'team.smart-target.analisis.index', 
            'icon' => 'fas fa-robot', 
            'permission_name' => 'smart-target',
            'parent_id' => $manajemenData->id,
            'order' => 36, 
            'is_active' => true,
        ]);

        $pengaturan = MenuItem::create([
            'name' => 'Pengaturan',
            'route_name' => null, 
            'icon' => 'fas fa-cogs',
            'permission_name' => 'manage-settings', 
            'parent_id' => null,
            'order' => 40,
            'is_active' => true,
        ]);
        
        $permissionsIndex = MenuItem::create([
            'name' => 'Manajemen Izin',
            'route_name' => 'admin.permissions.index', 
            'icon' => 'fas fa-user-lock',
            'permission_name' => 'manage-permissions', 
            'parent_id' => $pengaturan->id,
            'order' => 41,
            'is_active' => true,
        ]);

        $menuIndex = MenuItem::create([
            'name' => 'Manajemen Menu',
            'route_name' => 'admin.menu.index', 
            'icon' => 'fas fa-bars',
            'permission_name' => 'manage-menus', 
            'parent_id' => $pengaturan->id,
            'order' => 42,
            'is_active' => true,
        ]);

        // --- Item Menu BARU untuk Hirarki ---
        $hierarchyIndex = MenuItem::create([
            'name' => 'Manajemen Hirarki',
            'route_name' => 'admin.hierarchies.index', 
            'icon' => 'fas fa-project-diagram', 
            'permission_name' => 'manage-hierarchy-levels', // Izin untuk menu ini
            'parent_id' => $pengaturan->id, // Letakkan di bawah menu "Pengaturan"
            'order' => 43, 
            'is_active' => true,
        ]);

        $supervisorWorkers = MenuItem::create([
            'name' => 'Manajemen Queue Workers',
            'route_name' => 'admin.supervisor.index',
            'icon' => 'fas fa-cogs', // Atau ikon lain yang sesuai
            'permission_name' => 'manage-workers',
            'parent_id' => $pengaturan->id, // Ditempatkan di bawah Pengaturan
            'order' => 44, 
            'is_active' => true,
        ]);
        // --- Akhir Item Menu BARU ---


        // --- Asosiasi Menu dengan Peran (Melalui Tabel Pivot role_menu) ---
        if ($adminRole) {
            $adminRole->menuItems()->attach([
                $dashboard->id,
                $manajemenPengguna->id, 
                $usersIndex->id, 
                $usersCreate->id,
                $manajemenData->id, 
                $mappingvalidasi->id,
                $dataPelangganDashboard->id,
                $DataPelangganIndex->id,
                $pengaturan->id, 
                $permissionsIndex->id, 
                $menuIndex->id,
                $hierarchyIndex->id,
                $supervisorWorkers->id,
                $queueImportData->id,
            ]);
        }

        if ($tlUserRole) {
            $tlUserRole->menuItems()->attach([
                $manajemenPengguna->id,
                $usersIndex->id,
                $usersCreate->id,
                $manajemenData->id,
                $top2tl->id,
                $mapping->id,
                $mappingvalidasi->id,
                $pengaturan->id, 
            ]);
        }

        if ($appUserRole) {
            $appUserRole->menuItems()->attach([
                $manajemenPengguna->id,
                $usersIndex->id,
                $manajemenData->id,
                $mappingvalidasi->id,
            ]);
        }

        if ($executiveUserRole) {
            $executiveUserRole->menuItems()->attach([
            ]);
        }
    }
}