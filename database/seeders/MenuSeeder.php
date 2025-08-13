<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Menonaktifkan pemeriksaan foreign key sementara
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('role_menu')->truncate();
        MenuItem::truncate();

        // 1. Buat Menu Item Utama
        $dashboardAdmin = MenuItem::create([
            'name' => 'Dashboard Admin',
            'route_name' => 'admin.dashboard',
            'icon' => 'fas fa-tachometer-alt', // Contoh ikon Font Awesome
            'order' => 10,
        ]);

        $userManagement = MenuItem::create([
            'name' => 'Manajemen Pengguna',
            'route_name' => 'admin.users.index',
            'icon' => 'fas fa-users',
            'order' => 20,
        ]);

        $reports = MenuItem::create([
            'name' => 'Laporan',
            'icon' => 'fas fa-chart-line',
            'order' => 30,
        ]);

        $appUserDashboard = MenuItem::create([
            'name' => 'Dashboard Utama',
            'route_name' => 'app_user.dashboard',
            'icon' => 'fas fa-home',
            'order' => 10,
        ]);

        $executiveDashboard = MenuItem::create([
            'name' => 'Dashboard Executive',
            'route_name' => 'executive.dashboard',
            'icon' => 'fas fa-briefcase',
            'order' => 10,
        ]);

        // Contoh sub-menu untuk Laporan
        $reportSummary = MenuItem::create([
            'name' => 'Ringkasan Laporan',
            'route_name' => 'dashboard', // Contoh rute umum
            'icon' => 'far fa-circle',
            'parent_id' => $reports->id,
            'order' => 10,
        ]);
        $reportDetail = MenuItem::create([
            'name' => 'Detail Laporan',
            'route_name' => 'dashboard', // Contoh rute umum
            'icon' => 'far fa-circle',
            'parent_id' => $reports->id,
            'order' => 20,
        ]);


        // 2. Kaitkan Menu Item dengan Peran di tabel 'role_menu'
        // Admin
        DB::table('role_menu')->insert([
            ['role' => 'admin', 'menu_item_id' => $dashboardAdmin->id],
            ['role' => 'admin', 'menu_item_id' => $userManagement->id],
            ['role' => 'admin', 'menu_item_id' => $reports->id],
            ['role' => 'admin', 'menu_item_id' => $reportSummary->id], // Sub-menu juga dikaitkan
            ['role' => 'admin', 'menu_item_id' => $reportDetail->id], // Sub-menu juga dikaitkan
        ]);

        // App User
        DB::table('role_menu')->insert([
            ['role' => 'app_user', 'menu_item_id' => $appUserDashboard->id],
            ['role' => 'app_user', 'menu_item_id' => $reports->id], // App user bisa lihat menu Laporan utama
            ['role' => 'app_user', 'menu_item_id' => $reportSummary->id], // Tapi mungkin hanya sub-menu ringkasan
        ]);

        // Executive User
        DB::table('role_menu')->insert([
            ['role' => 'executive_user', 'menu_item_id' => $executiveDashboard->id],
            ['role' => 'executive_user', 'menu_item_id' => $reports->id],
            ['role' => 'executive_user', 'menu_item_id' => $reportSummary->id],
            ['role' => 'executive_user', 'menu_item_id' => $reportDetail->id], // Executive user bisa lihat detail juga
        ]);

        $this->command->info('Menu items and role associations seeded!');

        // Mengaktifkan kembali pemeriksaan foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
