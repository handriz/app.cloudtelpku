<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        // Gates untuk fitur spesifik
        Gate::define('manage-users', function (User $user) {
            return $user->role === 'admin'; // Hanya admin yang bisa mengelola pengguna
        });

        Gate::define('view-all-reports', function (User $user) {
            return in_array($user->role, ['admin', 'executive_user']); // Admin dan Executive bisa melihat semua laporan
        });

        // Tambahkan gates lainnya sesuai kebutuhan fitur aplikasi Anda
        // Contoh:
        // Gate::define('access-ulp-data', function (User $user) {
        //     return $user->role === 'app_user' && !empty($user->hierarchy_level_code);
        // });
    }
}
