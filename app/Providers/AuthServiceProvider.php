<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Daftarkan Gate secara dinamis dari database
        try {
            // Ambil semua izin dari tabel 'permissions'
            // Pastikan tabel 'permissions' sudah ada saat ini dijalankan (melalui migrasi & seeder)
            foreach (Permission::all() as $permission) {
                Gate::define($permission->name, function (User $user) use ($permission) {
                    // Cek apakah peran user yang login memiliki izin ini
                    // Asumsi: User memiliki relasi belongsTo ke Role, dan Role memiliki relasi many-to-many ke Permission
                    return $user->role && $user->role->hasPermissionTo($permission->name);
                });
            }
        } catch (\Exception $e) {
            // Tangani kasus di mana tabel 'permissions' mungkin belum ada saat boot
            // Ini umum terjadi saat menjalankan 'php artisan migrate:fresh' pertama kali
            \Log::warning('Could not register dynamic gates: ' . $e->getMessage());
        }


        // Opsional: Super admin bypass. Jika peran 'admin' memiliki semua izin.
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });
    }
}
