<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;
    protected $table = 'menu_items';
    protected $fillable = [
        'name',
        'route_name',
        'icon',
        'permission_name',
        'parent_id',
        'url',
        'order',
        'is_active',
    ];

    /**
     * Relasi untuk mendapatkan parent dari item menu ini (untuk sub-menu).
     */
    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * Relasi untuk mendapatkan anak-anak dari item menu ini (untuk menu utama).
     * Diurutkan berdasarkan kolom 'order'.
     */
    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order');
    }

    /**
     * Relasi many-to-many ke peran (roles) melalui tabel pivot 'role_menu'.
     * Ini memungkinkan kita untuk menentukan peran mana yang dapat melihat item menu ini.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_menu', 'menu_item_id', 'role_id');
    }

}
