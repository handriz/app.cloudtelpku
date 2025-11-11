<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'objectid','idpel', 'user_pendataan', 'enabled', 'nokwhmeter', 'merkkwhmeter',
        'tahun_buat', 'mcb', 'type_pbts', 'type_kotakapp', 'latitudey', 'longitudex',
        'namagd', 'jenis_kabel', 'ukuran_kabel', 'ket_survey', 'deret', 'sr','latitudey_sr', 'longitudex_sr',
        'ket_validasi', 'foto_kwh', 'foto_bangunan','validation_notes','validation_data','is_validated','user_validasi',
    ];

    protected $casts = [
        'validation_data' => 'array',
        'is_validated' => 'boolean',
    ];
}
