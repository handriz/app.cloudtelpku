<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterKddk extends Model
{
    use HasFactory;

    // Tentukan nama tabel secara eksplisit
    protected $table = 'master_kddk';
    
    // Kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'kode_kddk',          // Kunci Utama Bisnis (Unique)
        'unitup',             // Unit Pemilik
        'default_petugas_id', // Petugas Default (Opsional)
        'keterangan',         // Deskripsi Rute
        'is_active'
    ];

    /**
     * Mutator: Memastikan Kode KDDK selalu tersimpan dalam HURUF BESAR (Uppercase).
     * Contoh: User input 'a1b...' -> Disimpan 'A1B...'
     */
    public function setKodeKddkAttribute($value)
    {
        $this->attributes['kode_kddk'] = strtoupper($value);
    }

    /**
     * Relasi ke User (Petugas Default)
     * Satu KDDK bisa memiliki satu petugas penanggung jawab default.
     */
    public function defaultPetugas()
    {
        return $this->belongsTo(User::class, 'default_petugas_id');
    }

    /**
     * Relasi ke MappingKddk (Data Pelanggan)
     * Ini adalah relasi kunci untuk Tree View.
     * MasterKddk (One) -> punya banyak -> MappingKddk (Many)
     * * Foreign Key di MappingKddk: 'kddk'
     * Local Key di MasterKddk: 'kode_kddk'
     */
    public function pelanggan()
    {
        return $this->hasMany(MappingKddk::class, 'kddk', 'kode_kddk');
    }
}