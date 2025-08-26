<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
use App\Models\HierarchyLevel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_approved',
        'role_id',
        'hierarchy_level_code', 
        'dashboard_route_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_approved' => 'boolean', 
    ];

    /**
     * Dapatkan semua izin yang diberikan kepada peran pengguna.
     */

    // Relasi satu-ke-banyak (User belongs to Role)
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->role_id = $role->id;
            $this->save();
        }
    }
    
    public function hierarchyLevel()
    {
        // Menghubungkan kolom 'hierarchy_level_code' di tabel users
        // dengan kolom 'code' di tabel hierarchy_levels
        return $this->belongsTo(HierarchyLevel::class, 'hierarchy_level_code', 'code');
    }

    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        // Pastikan user memiliki peran
        if (!$this->role) {
            return collect(); // Kembalikan koleksi kosong jika tidak ada peran
        }

        $cacheKey = 'user_permissions_for_role_' . $this->role->name; // Gunakan nama peran untuk cache key

        return Cache::remember($cacheKey, now()->addMinutes(60), function () {
            // Dapatkan semua nama izin yang terkait dengan peran user ini
            // Melalui relasi Role dan pivot role_permissions
            return $this->role->permissions->pluck('name');
        });
    }
}
