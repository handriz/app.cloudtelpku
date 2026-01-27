<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatrixSummary extends Model
{
    use HasFactory;

    // Nama tabel di database (sesuai migrasi)
    protected $table = 'matrix_summaries';

    // Kolom yang boleh diisi secara massal (Mass Assignment)
    protected $fillable = [
        // --- IDENTITAS UNIT ---
        'unit_code',
        'unit_name',
        'parent_code',
        'region_code',

        // --- 1. TARGET (Data Master) ---
        'target_pelanggan',
        'target_prabayar',
        'target_pascabayar',

        // --- 2. SUDAH KDDK (Mapping) ---
        'sudah_kddk',
        'sudah_kddk_prabayar',
        'sudah_kddk_pascabayar',

        // --- 3. VALIDASI (Survey) ---
        'realisasi_survey',
        'valid',
        'ditolak',

        // --- 4. PERSENTASE ---
        'percentage',
    ];

    // Casting tipe data agar output JSON/PHP sesuai tipenya (bukan string semua)
    protected $casts = [
        'target_pelanggan'      => 'integer',
        'target_prabayar'       => 'integer',
        'target_pascabayar'     => 'integer',
        'sudah_kddk'            => 'integer',
        'sudah_kddk_prabayar'   => 'integer',
        'sudah_kddk_pascabayar' => 'integer',
        'realisasi_survey'      => 'integer',
        'valid'                 => 'integer',
        'ditolak'               => 'integer',
        'percentage'            => 'float', // Penting agar tidak dianggap string
    ];

    /**
     * Scope helper untuk mengambil data berdasarkan Unit Code dengan cepat.
     */
    public function scopeByUnit($query, $unitCode)
    {
        return $query->where('unit_code', $unitCode);
    }
}