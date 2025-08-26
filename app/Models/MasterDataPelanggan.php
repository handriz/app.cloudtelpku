<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterDataPelanggan extends Model
{
    use HasFactory;

    protected $table = 'master_data_pelanggan';

    protected $fillable = [
        'V_BULAN_REKAP',
        'UNITUPI',
        'UNITAP',
        'UNITUP',
        'IDPEL',
        'TARIF',
        'DAYA',
        'KOGOL',
        'KDDK',
        'NOMOR_METER_KWH',
        'MERK_METER_KWH',
        'TAHUN_TERA_METER_KWH',
        'TAHUN_BUAT_METER_KWH',
        'CT_PRIMER_KWH',
        'CT_SEKUNDER_KWH',
        'PT_PRIMER_KWH',
        'PT_SEKUNDER_KWH',
        'FKMKWH',
        'JENISLAYANAN',
        'STATUS_DIL',
        'NOMOR_GARDU',
        'NAMA_GARDU',
        'KOORDINAT_X',
        'KOORDINAT_Y',
        'KDPEMBMETER',
        'KDAM',
        'VKRN',
    ];

        protected $casts = [
        'KOORDINAT_X' => 'decimal:8', // 8 digit setelah koma
        'KOORDINAT_Y' => 'decimal:8', // 8 digit setelah koma
    ];
}
