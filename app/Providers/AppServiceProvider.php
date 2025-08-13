<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB; 

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // View Composer untuk Sidebar Navigasi
        // Ini akan berjalan setiap kali tampilan yang cocok (misalnya 'layouts.navigation' dari Breeze)
        // atau semua tampilan dashboard dimuat.
        View::composer(['layouts.navigation','layouts.sidebar', 'dashboards.*'], function ($view) {
            $menuItems = collect(); // Inisialisasi koleksi kosong

            if (Auth::check()) {
                $userRole = Auth::user()->role;

                // Dapatkan ID item menu yang terkait dengan peran pengguna ini dari tabel pivot.
                $accessibleMenuItemIds = DB::table('role_menu')
                                            ->where('role', $userRole)
                                            ->pluck('menu_item_id')
                                            ->toArray(); // Konversi ke array untuk 'whereIn'

                // Dapatkan semua item menu utama (parent_id = null) yang ID-nya ada dalam daftar yang bisa diakses.
                // Muat juga anak-anaknya (`children`) dan urutkan.
                $menuItems = MenuItem::whereNull('parent_id')
                    ->whereIn('id', $accessibleMenuItemIds) // Filter menu item yang bisa diakses
                    ->with(['children' => function ($query) use ($accessibleMenuItemIds) {
                        // Pastikan anak-anak yang dimuat juga bisa diakses oleh peran ini
                        $query->whereIn('id', $accessibleMenuItemIds);
                    }])
                    ->orderBy('order')
                    ->get();

                // Filter lebih lanjut jika ada anak-anak yang tidak seharusnya tampil
                $menuItems = $menuItems->filter(function ($menuItem) {
                    if ($menuItem->children->isNotEmpty()) {
                        // Jika item menu punya anak, pastikan ada anak yang bisa diakses
                        $menuItem->setRelation('children', $menuItem->children->where('parent_id', $menuItem->id));
                        return $menuItem->children->isNotEmpty();
                    }
                    return true; // Item menu tanpa anak selalu tampil jika parent-nya bisa diakses
                });
            }

            $view->with('menuItems', $menuItems);
        });
    }
}
