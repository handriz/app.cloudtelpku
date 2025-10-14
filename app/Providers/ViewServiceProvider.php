<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache; // Import Cache Facade

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.sidebar', function ($view) {
            $user = Auth::user();
            $menuItems = collect();

            if ($user && $user->role) {
                $cacheKey = 'menu_for_role_' . $user->role->id;

                // Ambil data menu dari cache atau database
                $menuItems = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user) {
                    $role = $user->role->load(['menuItems' => function($query) {
                        $query->where('is_active', true)->orderBy('order');
                    }]);

                    $allRoleMenus = $role->menuItems->where('name', '!=', 'Dashboard');

                    // Bangun struktur menu bertingkat
                    $nestedMenus = $allRoleMenus->whereNull('parent_id')->map(function ($menu) use ($allRoleMenus) {
                        $menu->setRelation('children', $allRoleMenus->where('parent_id', $menu->id));
                        return $menu;
                    });
                    
                    return $nestedMenus;
                });
            }
            
            // Tambahkan flag 'is_active' pada setiap item menu
            $menuItems = $menuItems->map(function ($menu) {
                $menu->is_active = $this->isMenuItemActive($menu);
                $menu->children = $menu->children->map(function ($child) {
                    $child->is_active = $this->isMenuItemActive($child);
                    return $child;
                });
                return $menu;
            });
            $view->with('menuItems', $menuItems);
        });

        View::composer('layouts.navigation', function ($view) {
        if (Auth::check()) {
            $view->with('notifications', Auth::user()->unreadNotifications);
        }
    });
    }

    // Fungsi pembantu untuk memeriksa status aktif
    private function isMenuItemActive($menuItem): bool
    {
        if ($menuItem->route_name && Route::has($menuItem->route_name)) {
            return request()->routeIs($menuItem->route_name);
        }
        
        if ($menuItem->url) {
            $path = ltrim(parse_url($menuItem->url, PHP_URL_PATH), '/');
            return request()->is($path);
        }
        
        // Periksa jika salah satu anak aktif (untuk menu parent)
        if ($menuItem->children && $menuItem->children->isNotEmpty()) {
            foreach ($menuItem->children as $child) {
                if ($this->isMenuItemActive($child)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}