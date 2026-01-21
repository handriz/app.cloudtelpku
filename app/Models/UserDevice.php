<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = ['is_blocked' => 'boolean', 'last_login_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}