<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::all();
        $allPermissions = Permission::orderBy('name')->get();

        $selectedRole = null;
        $currentRolePermissionsIds = [];
        $persistedCheckedPermissions = [];

        if ($request->has('role_id')) {
            $selectedRoleId = $request->input('role_id');
            $selectedRole = Role::find($selectedRoleId);
            if ($selectedRole) {
                $currentRolePermissionsIds = $selectedRole->permissions->pluck('id')->map(fn($id) => (string)$id)->toArray();
            }
        }

        // KUNCI PERBAIKAN: Ambil status izin yang dicentang dari URL
        if ($request->has('checked_permissions')) {
            $persistedCheckedPermissions = json_decode($request->input('checked_permissions'), true);
        }

        // Gabungkan izin yang sudah ada di database dengan izin yang dicentang dari URL
        // Ini memastikan status tidak hilang saat navigasi
        $combinedPermissions = array_unique(array_merge($currentRolePermissionsIds, $persistedCheckedPermissions));

        $groupedPermissions = $allPermissions->groupBy(function ($permission) {
            $name = $permission->name;

            if (Str::contains($name, '-permission')) {
                return 'Manajemen Izin';
            } elseif (Str::contains($name, '-dashboard')) {
                return 'Akses Dashboard';
            } elseif (Str::contains($name, '-user')) {
                return 'Manajemen Pengguna';
            } elseif (Str::contains($name, '-menu')) {
                return 'Manajemen Menu';
            } elseif (Str::contains($name, '-hierarchy-level')) {
                return 'Manajemen Hirarki';
            } elseif (Str::contains($name, 'master-data') || Str::contains($name, 'master_data')) {
                return 'Manajemen Master Data';
            } elseif (Str::startsWith($name, 'view-') || Str::startsWith($name, 'data-dashboard')) {
                return 'Manajemen View';
            } elseif (Str::contains($name, 'manage-workers')) {
                return 'Manajemen Pekerja Queue';
            }

            return 'Lain-lain';

        })->sortKeys();

        $perPage = 11;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $currentGroupNames = $groupedPermissions->keys()->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedGroupedPermissions = $groupedPermissions->filter(function ($value, $key) use ($currentGroupNames) {
            return $currentGroupNames->contains($key);
        });

        $paginator = new LengthAwarePaginator(
            $paginatedGroupedPermissions,
            $groupedPermissions->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Teruskan data yang dibutuhkan ke view

        if ($request->has('is_ajax')) {
            return view('admin.permissions.partials.index_content', compact('paginator', 'roles', 'selectedRole', 'combinedPermissions', 'paginatedGroupedPermissions'))->render();
        }
        return view('admin.permissions.index', compact('paginator', 'roles', 'selectedRole', 'combinedPermissions', 'paginatedGroupedPermissions'));
    }

    /**
     * Simpan pembaruan izin peran.
     */
    public function updateRolePermissions(Request $request)
    {

        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'all_checked_permissions' => 'nullable|string',
        ]);

        $targetRoleId = $request->input('role_id');
        $targetRole = Role::findOrFail($targetRoleId);

        $permissionIdsToSync = json_decode($request->input('all_checked_permissions', '[]'));

        DB::transaction(function () use ($targetRole, $permissionIdsToSync) {
            // 1. Sinkronkan izin pada tabel perantara role_permissions
            $targetRole->permissions()->sync($permissionIdsToSync);

            // Ambil semua permission_name yang sesuai dengan permission_id yang disinkronkan
            $syncedPermissionNames = Permission::whereIn('id', $permissionIdsToSync)->pluck('name');

            $menuItemsToSync = MenuItem::whereIn('permission_name', $syncedPermissionNames)->pluck('id');
        
            // 3. Sinkronkan item menu pada tabel perantara role_menu
            $targetRole->menuItems()->sync($menuItemsToSync);
        });
        
        Cache::forget('permissions.all');

        return redirect()->route('admin.permissions.index', ['role_id' => $targetRole->id])->with('success', "Izin untuk peran '{$targetRole->name}' berhasil diperbarui.");
    }
}