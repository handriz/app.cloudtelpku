<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'role_menu', 'role_id', 'menu_item_id');
    }

    // BARU: Relasi many-to-many dengan Permission
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    // BARU: Metode helper untuk mengecek apakah peran memiliki izin
    public function hasPermissionTo($permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }
}
