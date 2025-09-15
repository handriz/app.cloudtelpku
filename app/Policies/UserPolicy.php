<?php

namespace App\Policies;

use App\Models\User;
use App\Models\HierarchyLevel;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Memberikan akses penuh kepada admin untuk semua aksi, kecuali yang diatur secara spesifik.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null; // Lanjutkan ke pemeriksaan method policy lainnya
    }

    /**
     * Menentukan apakah user bisa membuat user baru.
     */
    public function create(User $loggedInUser): bool
    {
        return $loggedInUser->hasRole('admin') || $loggedInUser->hasRole('tl_user');
    }

    /**
     * Menentukan apakah user bisa mengupdate data user lain.
     */
    public function update(User $loggedInUser, User $userToUpdate): bool
    {
        // Izinkan user mengedit profilnya sendiri
        if ($loggedInUser->id === $userToUpdate->id) {
            return true;
        }

        // tl_user hanya bisa mengedit user di bawah hierarkinya
        if ($loggedInUser->hasRole('tl_user')) {
            return $this->isHierarchyDescendantOrSame(
                $loggedInUser->hierarchy_level_code,
                $userToUpdate->hierarchy_level_code
            );
        }

        // Peran lain tidak bisa mengedit user lain
        return false;
    }

    /**
     * Menentukan apakah user bisa menghapus data user lain.
     */
    public function delete(User $loggedInUser, User $userToDelete): bool
    {
        // ===== PERLINDUNGAN BARU =====
        // JANGAN PERNAH izinkan user dengan peran 'admin' dihapus oleh siapa pun.
        if ($userToDelete->hasRole('admin')) {
            return false;
        }
        // ===== AKHIR PERLINDUNGAN =====

        // Aturan lainnya sama seperti update
        return $this->update($loggedInUser, $userToDelete);
    }

    /**
     * Helper method: Periksa hierarki.
     */
    protected function isHierarchyDescendantOrSame(string $parentHierarchyCode, string $childHierarchyCode): bool
    {
        if ($parentHierarchyCode === $childHierarchyCode) return true;
        $allHierarchyLevels = HierarchyLevel::all()->keyBy('code');
        $current = $allHierarchyLevels->get($childHierarchyCode);
        while ($current) {
            if ($current->parent_code === $parentHierarchyCode) return true;
            $current = $allHierarchyLevels->get($current->parent_code);
        }
        return false;
    }
}