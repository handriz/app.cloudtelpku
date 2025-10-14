<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\HierarchyScope;

class MasterDataPelanggan extends Model
{
    use HasFactory;

    protected $table = 'master_data_pelanggan';

    protected $fillable = [
        'v_bulan_rekap',
        'unitupi',
        'unitap',
        'unitup',
        'idpel',
        'tarif',
        'daya',
        'kogol',
        'kddk',
        'nomor_meter_kwh',
        'merk_meter_kwh',
        'tahun_tera_meter_kwh',
        'tahun_buat_meter_kwh',
        'ct_primer_kwh',
        'ct_sekunder_kwh',
        'pt_primer_kwh',
        'pt_sekunder_kwh',
        'fkmkwh',
        'jenislayanan',
        'status_dil',
        'nomor_gardu',
        'nama_gardu',
        'koordinat_x',
        'koordinat_y',
        'kdpembmeter',
        'kdam',
        'vkrn',
    ];

    protected $casts = [
        'koordinat_x' => 'decimal:8', // 8 digit setelah koma
        'koordinat_y' => 'decimal:8', // 8 digit setelah koma
    ];

    protected static function booted(): void
    {
        // Terapkan scope secara otomatis
        static::addGlobalScope(new HierarchyScope);
    }
}
