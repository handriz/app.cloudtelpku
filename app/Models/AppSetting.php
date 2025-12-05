<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $guarded = ['id'];

    // Helper untuk mengambil nilai dengan Fallback
    public static function findValue($key, $hierarchyCode = null, $default = null)
    {
        // 1. Cek Setting Unit Spesifik (Prioritas Utama)
        if ($hierarchyCode) {
            $unitSetting = self::where('key', $key)->where('hierarchy_code', $hierarchyCode)->first();
            if ($unitSetting) return self::castValue($unitSetting);
        }

        // 2. Cek Setting Global (Prioritas Kedua)
        $globalSetting = self::where('key', $key)->whereNull('hierarchy_code')->first();
        if ($globalSetting) return self::castValue($globalSetting);

        // 3. Return Default
        return $default;
    }

    // Helper Casting
    private static function castValue($setting)
    {
        if ($setting->type === 'json') return json_decode($setting->value, true);
        if ($setting->type === 'integer') return intval($setting->value);
        if ($setting->type === 'boolean') return (bool) $setting->value;
        return $setting->value;
    }
}