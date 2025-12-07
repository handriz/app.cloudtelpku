<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $guarded = ['id'];

    /**
     * Helper Cerdas: Mengambil nilai setting dengan prioritas:
     * 1. Lokal (Unit User) -> Jika ada, pakai ini.
     * 2. Global (Admin) -> Jika tidak ada lokal, pakai ini.
     * 3. Default -> Jika tidak ada di DB, pakai nilai default.
     */
    public static function findValue($key, $hierarchyCode = null, $default = null)
    {
        // 1. Cek Setting Unit Lokal (Prioritas Tinggi)
        if ($hierarchyCode) {
            $unitSetting = self::where('key', $key)
                               ->where('hierarchy_code', $hierarchyCode)
                               ->first();
            
            if ($unitSetting) return self::castValue($unitSetting);
        }

        // 2. Cek Setting Global (Fallback)
        $globalSetting = self::where('key', $key)
                             ->whereNull('hierarchy_code')
                             ->first();

        if ($globalSetting) return self::castValue($globalSetting);

        // 3. Return Default jika tidak ditemukan sama sekali
        return $default;
    }

    /**
     * Helper Internal: Mengubah string database menjadi tipe data asli
     */
    private static function castValue($setting)
    {
        if ($setting->type === 'json') {
            // Decode JSON menjadi Array Asosiatif
            return json_decode($setting->value, true);
        }
        if ($setting->type === 'integer') {
            return intval($setting->value);
        }
        if ($setting->type === 'boolean') {
            return (bool) $setting->value;
        }
        
        // Default String
        return $setting->value;
    }
}