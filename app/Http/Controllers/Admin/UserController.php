<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\HierarchyLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Memeriksa apakah suatu hierarki adalah anak dari hierarki lain.
     *
     * @param string $parentHierarchyCode Kode hierarki induk.
     * @param string $childHierarchyCode Kode hierarki yang akan diperiksa.
     * @param \Illuminate\Support\Collection $allHierarchyLevels Semua level hierarki yang tersedia.
     * @return bool
     */
    protected function isHierarchyDescendantOrSame(string $parentHierarchyCode, string $childHierarchyCode, $allHierarchyLevels): bool
    {
        if ($parentHierarchyCode === $childHierarchyCode) {
            return true;
        }

        $current = $allHierarchyLevels->where('code', $childHierarchyCode)->first();

        while ($current && $current->parent_code !== null) {
            if ($current->parent_code === $parentHierarchyCode) {
                return true;
            }
            $current = $allHierarchyLevels->where('code', $current->parent_code)->first();
        }

        return false;
    }

    /**
     * Tampilkan daftar pengguna.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $loggedInUser = Auth::user();
        $query = User::with(['role', 'hierarchyLevel']);
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();

        if ($loggedInUser->hasRole('admin')) {
            // Admin bisa melihat semua pengguna
        } elseif ($loggedInUser->hasRole('tl_user')) {
            $manageableHierarchyCodes = $allHierarchyLevels->filter(function ($level) use ($loggedInUser, $allHierarchyLevels) {
                return $this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $level->code, $allHierarchyLevels);
            })->pluck('code')->toArray();
            $query->whereIn('hierarchy_level_code', $manageableHierarchyCodes);
        } elseif ($loggedInUser->hasRole('app_user')) {
            $query->where('hierarchy_level_code', $loggedInUser->hierarchy_level_code);
        } else {
            $query->where('id', $loggedInUser->id);
        }

        $users = $query->orderBy('name')->paginate(10);
        $roles = Role::all();
        $hierarchyLevels = $allHierarchyLevels;

        // KUNCI PERUBAHAN: Jika permintaan AJAX, kembalikan view parsial
        if ($request->has('is_ajax')) {
            return view('admin.users.partials.index_content', compact('users', 'roles', 'hierarchyLevels'))->render();
        }

        return view('admin.users.index', compact('users', 'roles', 'hierarchyLevels'));
    }

    /**
     * Tampilkan formulir untuk membuat pengguna baru.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $loggedInUser = Auth::user();
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();
        $roles = collect();
        $hierarchyLevels = collect();

        if ($loggedInUser->hasRole('admin')) {
            $roles = Role::all();
            $hierarchyLevels = $allHierarchyLevels;
        } elseif ($loggedInUser->hasRole('tl_user')) {
            $roles = Role::whereIn('name', ['app_user', 'executive_user'])->get();
            $hierarchyLevels = $allHierarchyLevels->filter(function ($level) use ($loggedInUser, $allHierarchyLevels) {
                return $this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $level->code, $allHierarchyLevels);
            });
        } elseif ($loggedInUser->hasRole('app_user')) {
            $roles = Role::where('name', 'app_user')->get();
            $hierarchyLevels = $allHierarchyLevels->where('code', $loggedInUser->hierarchy_level_code);
        } else {
            return abort(403, 'Unauthorized action.');
        }

        // KUNCI PERUBAHAN: Jika permintaan AJAX, kembalikan view parsial
        if ($request->has('is_ajax')) {
            return view('admin.users.create', compact('roles', 'hierarchyLevels'))->render();
        }

        return view('admin.users.create', compact('roles', 'hierarchyLevels'));
    }

    /**
     * Simpan pengguna baru.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $loggedInUser = Auth::user();
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'hierarchy_level_code' => ['nullable', 'string', 'max:255', 'exists:hierarchy_levels,code'],
            'dashboard_route_name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', 'exists:roles,id'],
            'is_approved' => ['boolean'],
        ]);

        if ($validator->fails()) {
            if ($request->has('is_ajax')) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $hierarchyCodeToUse = $request->hierarchy_level_code;
        $roleIdToUse = $request->role_id;

        if ($loggedInUser->hasRole('app_user')) {
            $hierarchyCodeToUse = $loggedInUser->hierarchy_level_code;
            $roleIdToUse = Role::where('name', 'app_user')->first()->id;
        } elseif ($loggedInUser->hasRole('tl_user')) {
            if (!$this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $request->hierarchy_level_code, $allHierarchyLevels)) {
                return $request->ajax() ? response()->json(['message' => 'Unauthorized action: Cannot create user in this hierarchy level.'], 403) : abort(403);
            }
            $allowedRoles = Role::whereIn('name', ['app_user', 'executive_user'])->pluck('id')->toArray();
            if (!in_array($request->role_id, $allowedRoles)) {
                return $request->ajax() ? response()->json(['message' => 'Unauthorized action: Cannot create user with this role.'], 403) : abort(403);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'hierarchy_level_code' => $hierarchyCodeToUse,
            'dashboard_route_name' => $request->dashboard_route_name,
            'role_id' => $roleIdToUse,
            'is_approved' => $request->boolean('is_approved'),
        ]);

        // KUNCI PERUBAHAN: Jika permintaan AJAX, kembalikan JSON
        if ($request->has('is_ajax')) {
            return response()->json(['message' => 'Pengguna berhasil ditambahkan!']);
        }
        
        return redirect()->route('manajemen-pengguna.users.index')->with('success', 'Pengguna berhasil ditambahkan!');
    }

    /**
     * Tampilkan formulir edit pengguna.
     *
     * @param Request $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, User $user)
    {
        $loggedInUser = Auth::user();
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();

        if (
            !$loggedInUser->hasRole('admin') &&
            (
                ($loggedInUser->hasRole('tl_user') && !$this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $user->hierarchy_level_code, $allHierarchyLevels)) ||
                ($loggedInUser->hasRole('app_user') && $loggedInUser->hierarchy_level_code !== $user->hierarchy_level_code) ||
                (!$loggedInUser->hasRole('tl_user') && !$loggedInUser->hasRole('app_user') && $loggedInUser->id !== $user->id)
            )
        ) {
            abort(403, 'Unauthorized action: You cannot edit this user.');
        }

        $roles = collect();
        $hierarchyLevels = collect();

        if ($loggedInUser->hasRole('admin')) {
            $roles = Role::all();
            $hierarchyLevels = $allHierarchyLevels;
        } elseif ($loggedInUser->hasRole('tl_user')) {
            $roles = Role::whereIn('name', ['app_user', 'executive_user'])->get();
            $hierarchyLevels = $allHierarchyLevels->filter(function ($level) use ($loggedInUser, $allHierarchyLevels) {
                return $this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $level->code, $allHierarchyLevels);
            });
        } elseif ($loggedInUser->hasRole('app_user')) {
            $roles = Role::where('name', 'app_user')->get();
            $hierarchyLevels = $allHierarchyLevels->where('code', $loggedInUser->hierarchy_level_code);
        } else {
            $roles = Role::where('id', $loggedInUser->role_id)->get();
            $hierarchyLevels = $allHierarchyLevels->where('code', $loggedInUser->hierarchy_level_code);
        }

        // KUNCI PERUBAHAN: Jika permintaan AJAX, kembalikan view parsial
        if ($request->has('is_ajax')) {
            return view('admin.users.edit', compact('user', 'roles', 'hierarchyLevels'))->render();
        }

        return view('admin.users.edit', compact('user', 'roles', 'hierarchyLevels'));
    }

    /**
     * Perbarui pengguna.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $loggedInUser = Auth::user();
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();

        if (
            !$loggedInUser->hasRole('admin') &&
            (
                ($loggedInUser->hasRole('tl_user') && !$this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $user->hierarchy_level_code, $allHierarchyLevels)) ||
                ($loggedInUser->hasRole('app_user') && $loggedInUser->hierarchy_level_code !== $user->hierarchy_level_code) ||
                (!$loggedInUser->hasRole('tl_user') && !$loggedInUser->hasRole('app_user') && $loggedInUser->id !== $user->id)
            )
        ) {
            return $request->ajax() ? response()->json(['message' => 'Unauthorized action: You cannot update this user.'], 403) : abort(403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id' => ['required', 'exists:roles,id'],
            'hierarchy_level_code' => ['nullable', 'string', 'max:255', 'exists:hierarchy_levels,code'],
            'dashboard_route_name' => ['nullable', 'string', 'max:255'],
            'is_approved' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            if ($request->has('is_ajax')) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $hierarchyCodeToUse = $request->hierarchy_level_code;
        $roleIdToUse = $request->role_id;

        if ($loggedInUser->hasRole('app_user')) {
            $hierarchyCodeToUse = $loggedInUser->hierarchy_level_code;
            $roleIdToUse = Role::where('name', 'app_user')->first()->id;
        } elseif ($loggedInUser->hasRole('tl_user')) {
            if (!$this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $request->hierarchy_level_code, $allHierarchyLevels)) {
                return $request->ajax() ? response()->json(['message' => 'Unauthorized action: Cannot update user to this hierarchy level.'], 403) : abort(403);
            }
            $allowedRoles = Role::whereIn('name', ['app_user', 'executive_user'])->pluck('id')->toArray();
            if (!in_array($request->role_id, $allowedRoles)) {
                return $request->ajax() ? response()->json(['message' => 'Unauthorized action: Cannot update user to this role.'], 403) : abort(403);
            }
        }

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->role_id = $roleIdToUse;
        $user->hierarchy_level_code = $hierarchyCodeToUse;
        $user->dashboard_route_name = $request->dashboard_route_name;
        $user->is_approved = $request->boolean('is_approved');
        $user->save();
        
        // KUNCI PERUBAHAN: Jika permintaan AJAX, kembalikan JSON
        if ($request->has('is_ajax')) {
            return response()->json(['message' => 'Pengguna berhasil diperbarui!']);
        }

        return redirect()->route('manajemen-pengguna.users.index')->with('success', 'Pengguna berhasil diperbarui!');
    }

    /**
     * Hapus pengguna.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, User $user)
    {
        $loggedInUser = Auth::user();
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();

        if (
            !$loggedInUser->hasRole('admin') &&
            (
                ($loggedInUser->hasRole('tl_user') && !$this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $user->hierarchy_level_code, $allHierarchyLevels)) ||
                ($loggedInUser->hasRole('app_user') && $loggedInUser->hierarchy_level_code !== $user->hierarchy_level_code) ||
                (!$loggedInUser->hasRole('tl_user') && !$loggedInUser->hasRole('app_user') && $loggedInUser->id !== $user->id)
            )
        ) {
            return $request->ajax() ? response()->json(['message' => 'Unauthorized action: You cannot delete this user.'], 403) : abort(403);
        }

        $user->delete();

        // KUNCI PERUBAHAN: Jika permintaan AJAX, kembalikan JSON
        if ($request->has('is_ajax')) {
            return response()->json(['message' => 'Pengguna berhasil dihapus!']);
        }
        
        return redirect()->route('manajemen-pengguna.users.index')->with('success', 'Pengguna berhasil dihapus!');
    }
}