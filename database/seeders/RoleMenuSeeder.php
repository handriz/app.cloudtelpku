<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class RoleMenuSeeder extends Seeder
{
    /**
     * Jalankan seed database.
     */
    public function run(): void
    {
        // Clear cache izin untuk memastikan data baru dimuat
        Cache::forget('user_permissions_for_role_admin');
        Cache::forget('user_permissions_for_role_app_user');
        Cache::forget('user_permissions_for_role_executive_user');
        // Tambahkan cache key untuk peran lain jika ada

        // --- PENTING: Nonaktifkan Foreign Key Checks Sementara ---
        Schema::disableForeignKeyConstraints();

        // Hapus semua data yang ada (gunakan delete() yang lebih reliable)
        DB::table('role_menu')->delete();

        // --- PENTING: Aktifkan Kembali Foreign Key Checks ---
        Schema::enableForeignKeyConstraints();


        // Dapatkan semua menu item yang relevan untuk setiap peran.
        // Gunakan kondisi yang lebih spesifik untuk mengambil item yang benar.
        $dashboardAdmin = MenuItem::where('route_name', 'admin.dashboard')->first();

        // Ambil menu induk 'Manajemen Pengguna' (yang route_name-nya NULL)
        $usersMenuParent = MenuItem::where('name', 'Manajemen Pengguna')->whereNull('route_name')->first();
        // Ambil sub-menu 'Daftar Pengguna' (yang punya route_name)
        $daftarPengguna = MenuItem::where('name', 'Daftar Pengguna')->where('route_name', 'admin.users.index')->first();

        $settingsMenuParent = MenuItem::where('name', 'Pengaturan')->whereNull('route_name')->first();
        $manajemenMenu = MenuItem::where('route_name', 'admin.menu.index')->first();
        $manajemenIzin = MenuItem::where('route_name', 'admin.permissions.index')->first();

        // Contoh: Menu untuk Peran 'app_user'
        $appUserDashboard = MenuItem::where('route_name', 'app_user.dashboard')->first();
        $appUserReports = MenuItem::where('route_name', 'app_user.reports')->first();

        // Contoh: Menu untuk Peran 'executive_user'
        $executiveDashboard = MenuItem::where('route_name', 'executive.dashboard')->first();


        // --- Hubungkan Peran 'admin' dengan Menu Items ---
        $adminMenuItemsIds = [];
        if ($dashboardAdmin) $adminMenuItemsIds[] = $dashboardAdmin->id;
        if ($usersMenuParent) $adminMenuItemsIds[] = $usersMenuParent->id; // Masukkan ID menu induk
        if ($daftarPengguna) $adminMenuItemsIds[] = $daftarPengguna->id;   // Masukkan ID sub-menu
        if ($settingsMenuParent) $adminMenuItemsIds[] = $settingsMenuParent->id;
        if ($manajemenMenu) $adminMenuItemsIds[] = $manajemenMenu->id;
        if ($manajemenIzin) $adminMenuItemsIds[] = $manajemenIzin->id;

        // PENTING: Gunakan array_unique untuk menghilangkan duplikasi ID sebelum memasukkan
        $adminMenuItemsIds = array_unique($adminMenuItemsIds);

        foreach ($adminMenuItemsIds as $menuId) {
            DB::table('role_menu')->insert([
                'role' => 'admin',
                'menu_item_id' => $menuId,
            ]);
        }

        // --- Hubungkan Peran 'app_user' dengan Menu Items ---
        $appUserMenuItemsIds = [];
        if ($appUserDashboard) $appUserMenuItemsIds[] = $appUserDashboard->id;
        if ($appUserReports) $appUserMenuItemsIds[] = $appUserReports->id;
        $appUserMenuItemsIds = array_unique($appUserMenuItemsIds);

        foreach ($appUserMenuItemsIds as $menuId) {
            DB::table('role_menu')->insert([
                'role' => 'app_user',
                'menu_item_id' => $menuId,
            ]);
        }
        
        // --- Hubungkan Peran 'executive_user' dengan Menu Items ---
        $executiveMenuItemsIds = [];
        if ($executiveDashboard) $executiveMenuItemsIds[] = $executiveDashboard->id;
        if ($appUserReports) $executiveMenuItemsIds[] = $appUserReports->id;
        $executiveMenuItemsIds = array_unique($executiveMenuItemsIds);

        foreach ($executiveMenuItemsIds as $menuId) {
            DB::table('role_menu')->insert([
                'role' => 'executive_user',
                'menu_item_id' => $menuId,
            ]);
        }
    }
}