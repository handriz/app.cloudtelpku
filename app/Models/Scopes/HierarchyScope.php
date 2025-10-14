<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use App\Models\HierarchyLevel;

class HierarchyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Pastikan ada pengguna yang sedang login
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->hasRole('admin')) {
                // Jika admin, jangan lakukan apa-apa (bisa lihat semua)
                return;
            }

            $userHierarchyCode = $user->hierarchy_level_code;

            if (!$userHierarchyCode) {
                // Jika tidak punya hirarki, jangan tampilkan apa-apa
                $builder->whereRaw('1 = 0'); // Kondisi yang selalu salah
                return;
            }
            
            // Logika ini sama seperti di Trait sebelumnya
            $level = HierarchyLevel::where('code', $userHierarchyCode)->with('parent.parent')->first();

            if ($level) {
                 if ($level->parent_code === null) {
                    $builder->where('unitupi', $userHierarchyCode);
                } elseif ($level->parent && $level->parent->parent_code === null) {
                    $builder->where('unitap', $userHierarchyCode);
                } else {
                    $builder->where('unitup', $userHierarchyCode);
                }
            } else {
                 $builder->whereRaw('1 = 0');
            }
        }
    }
}