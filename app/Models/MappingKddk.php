<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingKddk extends Model
{
    use HasFactory;
    protected $table = 'mapping_kddk';

    protected $fillable = [
        'objectid','idpel', 'user_pendataan','user_validasi', 'enabled', 'nokwhmeter', 'merkkwhmeter',
        'tahun_buat', 'mcb', 'type_pbts', 'type_kotakapp', 'latitudey', 'longitudex',
        'namagd', 'jenis_kabel', 'ukuran_kabel', 'ket_survey', 'deret', 'sr',
        'ket_validasi', 'foto_kwh', 'foto_bangunan',
    ];
}
