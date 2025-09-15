<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\HierarchyLevel;
use Illuminate\Support\Collection;
use App\Policies\UserPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Daftarkan Gate secara dinamis dari database
        try {
            // Daftarkan Gate secara dinamis dari database
            foreach (Permission::all() as $permission) {
                Gate::define($permission->name, function (User $user) use ($permission) {
                    return $user->role && $user->role->hasPermissionTo($permission->name);
                });
            }
        } catch (\Exception $e) {
            \Log::warning('Could not register dynamic gates: ' . $e->getMessage());
        }
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });

        Gate::define('manage-hierarchy-users', function (User $user, User $targetUser) {
            // Admin bisa mengelola semua pengguna
            if ($user->hasRole('admin')) {
                return true;
            }
            
            // TL User bisa mengelola pengguna yang berada di hierarki yang sama atau di bawahnya
            if ($user->hasRole('tl_user')) {
                return $this->isHierarchyDescendantOrSame($user->hierarchy_level_code, $targetUser->hierarchy_level_code, HierarchyLevel::all());
            }

            // App User hanya bisa mengelola dirinya sendiri (jika diperlukan)
            if ($user->hasRole('app_user')) {
                return $user->id === $targetUser->id;
            }

            // Executive User tidak dapat mengelola pengguna
            return false;
        });
    }

    /**
     * Memeriksa apakah suatu hierarki adalah anak dari hierarki lain.
     */
    protected function isHierarchyDescendantOrSame(string $parentHierarchyCode, string $childHierarchyCode, Collection $allHierarchyLevels): bool
    {
        if ($parentHierarchyCode === $childHierarchyCode) {
            return true;
        }

        $current = $allHierarchyLevels->where('code', $childHierarchyCode)->first();

        while ($current && $current->parent_code !== null) {
            if ($current->parent_code === $parentHierarchyCode) {
                return true;
            }
            $current = $allHierarchyLevels->where('code', $current->parent_code)->first();
        }

        return false;
    }
}
