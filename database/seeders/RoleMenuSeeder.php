<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB; 
use App\Models\MenuItem; 
use Illuminate\Database\Seeder;


class RoleMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hapus semua data yang ada untuk menghindari duplikasi saat seeding ulang
        DB::table('role_menu')->truncate();

        // Dapatkan semua menu item yang relevan untuk setiap peran
        $dashboardAdmin = MenuItem::where('route_name', 'admin.dashboard')->first();
        $usersMenu = MenuItem::where('name', 'Manajemen Pengguna')->first(); // Ini menu induk
        $daftarPengguna = MenuItem::where('route_name', 'admin.users.index')->first();
        $settingsMenu = MenuItem::where('name', 'Pengaturan')->first(); // Ini menu induk
        $manajemenMenu = MenuItem::where('route_name', 'admin.menu.index')->first();


        // --- Hubungkan Peran 'admin' dengan Menu Items ---
        if ($dashboardAdmin) {
            DB::table('role_menu')->insert([
                ['role' => 'admin', 'menu_item_id' => $dashboardAdmin->id],
            ]);
        }
        if ($usersMenu) {
             DB::table('role_menu')->insert([
                ['role' => 'admin', 'menu_item_id' => $usersMenu->id],
            ]);
        }
        if ($daftarPengguna) {
             DB::table('role_menu')->insert([
                ['role' => 'admin', 'menu_item_id' => $daftarPengguna->id],
            ]);
        }
        if ($settingsMenu) {
             DB::table('role_menu')->insert([
                ['role' => 'admin', 'menu_item_id' => $settingsMenu->id],
            ]);
        }
        if ($manajemenMenu) {
             DB::table('role_menu')->insert([
                ['role' => 'admin', 'menu_item_id' => $manajemenMenu->id],
            ]);
        }

        // --- Contoh: Menu untuk Peran 'app_user' ---
        // Asumsi ada menu item seperti 'Dashboard App User' dan 'Laporan Saya'
        $appUserDashboard = MenuItem::where('route_name', 'app_user.dashboard')->first();
        $appUserReports = MenuItem::where('route_name', 'app_user.reports')->first(); // Dari MenuSeeder

        if ($appUserDashboard) {
            DB::table('role_menu')->insert([
                ['role' => 'app_user', 'menu_item_id' => $appUserDashboard->id],
            ]);
        }
        if ($appUserReports) {
            DB::table('role_menu')->insert([
                ['role' => 'app_user', 'menu_item_id' => $appUserReports->id],
            ]);
        }

        // --- Contoh: Menu untuk Peran 'executive_user' ---
        $executiveDashboard = MenuItem::where('route_name', 'executive.dashboard')->first(); // Dari MenuSeeder

        if ($executiveDashboard) {
            DB::table('role_menu')->insert([
                ['role' => 'executive_user', 'menu_item_id' => $executiveDashboard->id],
            ]);
        }
    }
}
