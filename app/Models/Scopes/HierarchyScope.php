<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use App\Models\HierarchyLevel;

class HierarchyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Super admin (role 'admin') bisa melihat semua data, tanpa batasan hirarki
            if ($user->hasRole('admin')) {
                return; // Jangan terapkan scope
            }

            // Dapatkan kode hirarki pengguna yang login
            $userHierarchyCode = $user->hierarchy_level_code;

            if ($userHierarchyCode) {
                $allowedHierarchyCodes = [];
                HierarchyLevel::getDescendantCodes($userHierarchyCode, $allowedHierarchyCodes);

                // Filter query untuk hanya menyertakan data yang terkait dengan level hirarki yang diizinkan
                $builder->whereIn('hierarchy_level_code', $allowedHierarchyCodes);
            } else {
                // Jika pengguna tidak memiliki hierarchy_level_code, mungkin mereka tidak melihat data apa pun
                $builder->whereRaw('1 = 0'); // Query yang selalu false, tidak mengembalikan hasil
            }
        } else {
            // Jika pengguna tidak login, mereka tidak melihat data apa pun yang dibatasi hirarki
            $builder->whereRaw('1 = 0');
        }
    }
}
