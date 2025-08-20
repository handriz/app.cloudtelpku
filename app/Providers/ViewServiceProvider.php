<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\MenuItem;
use App\Models\Role;
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

                $userRole = $user->role; 

                if ($userRole) {
                    $allowedByRoleMenus = $userRole->menuItems()
                                                   ->where('is_active', true)
                                                   ->orderBy('order')
                                                   ->get();
                    $gatePermittedMenus = $allowedByRoleMenus->filter(function ($menuItem) use ($user) {
                         $canAccess = true;
                          if (!empty($menuItem->permission_name)) {
                            // Jika ada permission_name, cek Gate
                            $canAccess = Gate::forUser($user)->allows($menuItem->permission_name);
                            // Opsional: dump hasil setiap pengecekan Gate
                            // dump("Menu: " . $menuItem->name . ", Permission: " . $menuItem->permission_name . ", Allowed: " . ($canAccess ? 'TRUE' : 'FALSE'));
                        }
                        return $canAccess;
                    });
                    $nestedMenus = $gatePermittedMenus->whereNull('parent_id')->map(function ($menu) use ($gatePermittedMenus) {
                        $menu->setRelation('children', $gatePermittedMenus->where('parent_id', $menu->id)->sortBy('order'));
                        return $menu;
                    });
                     $menuItems = $nestedMenus->filter(function ($menu) {
                        return ($menu->route && !empty($menu->permission_name)) || $menu->children->isNotEmpty();
                    });
                    // dd($menuItems); // Ini adalah hasil akhir, harusnya tidak kosong jika menu ingin tampil
                }
            }

            $view->with('menuItems', $menuItems);
        });
    }
}
