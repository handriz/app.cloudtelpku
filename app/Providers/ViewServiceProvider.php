<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.sidebar', function ($view) {
            $menuItems = collect();

            if (Auth::check()) {
                $user = Auth::user();
                $role = $user->role;

                // KUNCI PERBAIKAN: Ambil item menu langsung dari relasi peran
                if ($role) {
                    // Ambil menu items yang terkait dengan role pengguna
                    $allRoleMenus = $role->menuItems()
                                         ->where('is_active', true)
                                         ->where('name', '!=', 'Dashboard') 
                                         ->orderBy('order')
                                         ->get();

                    // Bangun struktur menu bersarang dari koleksi yang sudah difilter
                    $nestedMenus = $allRoleMenus->whereNull('parent_id')->map(function ($menu) use ($allRoleMenus) {
                        $menu->setRelation('children', $allRoleMenus->where('parent_id', $menu->id)->sortBy('order'));
                        return $menu;
                    });
                
                    // Filter lagi untuk memastikan menu utama memiliki rute atau anak
                    $menuItems = $nestedMenus->filter(function ($menu) {
                        return !empty($menu->route_name) || $menu->children->isNotEmpty();
                    });
                }
            }
            
            $view->with('menuItems', $menuItems);
        });
    }
}