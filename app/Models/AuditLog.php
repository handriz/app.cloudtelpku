<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\MassPrunable;

class AuditLog extends Model
{
    use HasFactory;
    use MassPrunable;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Definisi query untuk data yang boleh dihapus otomatis (Prunable).
     * Contoh: Hapus data yang lebih tua dari 2 bulan.
     */
    public function prunable()
    {
        // Otomatis hapus data > 60 hari
        return static::where('created_at', '<=', now()->subMonths(2));
    }
}
