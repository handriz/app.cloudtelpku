<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'parent_id',
        'name',
        'icon',
        'route_name',
        'url',
        'permission_name',
        'order',
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
        // Relasi ini sedikit tidak konvensional karena kolom 'role' di tabel pivot
        // adalah string, bukan foreign key ke model Role.
        // Anda dapat menggunakannya untuk mendapatkan entri dari tabel pivot.
        // Jika Anda membuat model Role di masa mendatang, relasi ini akan lebih rapi.
        // Untuk saat ini, kita akan mengkueri tabel pivot secara langsung di View Composer.
        // Metode ini ada sebagai placeholder jika Anda ingin memperluasnya.
        return $this->belongsToMany(User::class, 'role_menu', 'menu_item_id', 'role', 'id', 'role');
        // Catatan: Relasi ini perlu disesuaikan jika 'role' di tabel pivot tidak merujuk ke User::role
        // Lebih baik: Kueri tabel pivot langsung di View Composer.
    }
}
