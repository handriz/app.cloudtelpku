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
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Tampilkan daftar pengguna.
     */
    public function index(Request $request)
    {
        $loggedInUser = Auth::user();
        $filteredHierarchyLevels = $this->getFilteredHierarchyLevels();
        $search = $request->input('search');

        $usersQuery = User::with(['role', 'hierarchyLevel'])
            ->whereIn('hierarchy_level_code', $filteredHierarchyLevels->pluck('code'))
            ->orderByDesc('created_at');

        if ($search) {
            $usersQuery->where(function ($query) use ($search) {
                 $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $usersQuery->simplePaginate(10)->withQueryString();

        $hierarchyLevels = $this->getFilteredHierarchyLevels();
        $roles = Role::all();

        if ($request->has('is_ajax')) {
            return view('admin.users.partials.index_content', compact('users', 'roles', 'hierarchyLevels', 'search'));
        }

        return view('admin.users.index', compact('users', 'roles', 'hierarchyLevels', 'search'));
    }

    /**
     * Tampilkan formulir untuk membuat pengguna baru.
     */
    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        $user = Auth::user();
        $roles = collect();
        $hierarchyLevels = $this->getFilteredHierarchyLevels();

        if ($user->hasRole('admin')) {
            $roles = Role::all();
        } elseif ($user->hasRole('team')) {
            $roles = Role::whereIn('name', ['team', 'executive_user','appuser'])->get();
        } elseif ($user->hasRole('appuser')) {
            $roles = Role::where('id', $user->role_id)->get();
            $hierarchyLevels = $hierarchyLevels->where('code', $user->hierarchy_level_code);
        } 

        if ($request->has('is_ajax')) {
            return view('admin.users.partials.create_content', compact('roles', 'hierarchyLevels'));
        }

        return view('admin.users.create', compact('roles', 'hierarchyLevels'));
    }

    public function store(Request $request)
    {

        $this->authorize('create', User::class);
    
        // Validasi data
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'hierarchy_level_code' => ['nullable', 'string', 'exists:hierarchy_levels,code'],
            'is_approved' => ['boolean'],
            'mobile_app' => $request->has('mobile_app'),
        ]);
    
        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }
    
        // Periksa izin pengguna yang sedang login
        $targetRole = Role::find($request->role_id);
        $targetHierarchyCode = $request->hierarchy_level_code;
    
        // Admin bisa membuat pengguna dengan peran/hierarki apa pun
        if (!$loggedInUser->hasRole('admin')) {
            // TL User hanya bisa membuat pengguna di hierarki di bawah atau sama
            if ($loggedInUser->hasRole('team')) {
                if (!$this->isHierarchyDescendantOrSame($loggedInUser->hierarchy_level_code, $targetHierarchyCode, $allHierarchyLevels)) {
                    return $request->ajax() ? response()->json(['message' => 'Unauthorized action: You cannot create a user at this hierarchy level.'], 403) : abort(403);
                }
            }
            
            // App User dan lainnya tidak diizinkan membuat pengguna
            else {
                return $request->ajax() ? response()->json(['message' => 'Unauthorized action: You do not have permission to create users.'], 403) : abort(403);
            }
        }
    
        // Simpan pengguna baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'hierarchy_level_code' => $request->hierarchy_level_code,
            'is_approved' => $request->has('is_approved'),
            'mobile_app' => $request->has('mobile_app'),
        ]);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => 'Pengguna berhasil ditambahkan!', 'user' => $user]);
        }
            return redirect()->route('manajemen-pengguna.users.index')->with('success', 'Pengguna berhasil ditambahkan!');
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $loggedInUser = Auth::user();
        $roles = Role::all();
        $hierarchyLevels = $this->getFilteredHierarchyLevels();

        $viewData = compact('user', 'roles', 'hierarchyLevels');

        if ($request->has('is_ajax')) {
            // Kita butuh view partial untuk modal. Buat file ini jika belum ada.
            return view('admin.users.partials.edit_content', compact('user', 'roles', 'hierarchyLevels'))->render();
        }
         return view('admin.users.edit', $viewData);
    }

    /**
     * Perbarui pengguna.
     */
    public function update(Request $request, User $user)
    {
        // Otorisasi menggunakan UserPolicy
        $this->authorize('update', $user);

        // 1. Validasi ringkas, otomatis handle error jika gagal
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id' => ['required', 'exists:roles,id'],
            'hierarchy_level_code' => [
                Rule::requiredIf(function () use ($request) {
                    $targetRole = Role::find($request->input('role_id'));
                    return $targetRole && $targetRole->name !== 'admin';
                }),
                'nullable', 'string', 'exists:hierarchy_levels,code'
            ],
            'is_approved' => ['boolean'], // Validator akan memastikan ini boolean
            'mobile_app' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // 2. Siapkan data untuk di-update dari hasil validasi
        $updateData = collect($validatedData)->except('password')->all();
          
        // 3. Tambahkan password HANYA jika diisi
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validatedData['password']);
        }

        // 4. (PENANGANAN ERROR) Tambahkan logika 'dashboard_route_name'
        $targetRole = Role::find($validatedData['role_id']);
        if ($targetRole) {
            switch ($targetRole->name) {
                case 'admin':
                    $updateData['dashboard_route_name'] = 'admin.dashboard';
                    break;
                case 'team':
                    $updateData['dashboard_route_name'] = 'team.dashboard';
                    break;
                case 'app_user':
                    $updateData['dashboard_route_name'] = 'app_user.dashboard';
                    break;
                case 'executive_user':
                    $updateData['dashboard_route_name'] = 'executive.dashboard';
                    break;
                default:
                    $updateData['dashboard_route_name'] = 'dashboard';
            }
        }
        
        // 5. Simpan semua data dalam satu perintah
        $user->update($updateData);

        return response()->json(['message' => 'Pengguna berhasil diperbarui!']);
    }

    /**
     * Hapus pengguna.
     *
     * @param Request $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, User $user)
    {
       $this->authorize('delete', $user);

       $user->delete();

       if ($request->wantsJson() || $request->ajax()) {
            // Beri status 200 OK agar konsisten dan tidak memicu error 204
            return response()->json(['message' => 'Pengguna berhasil dihapus!'], 200);
        }
        
        return redirect()->route('manajemen-pengguna.users.index')->with('success', 'Pengguna berhasil dihapus!');
    }

    protected function getFilteredHierarchyLevels(): Collection
    {
        $loggedInUser = Auth::user();
        $allHierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get();
        $filteredHierarchyLevels = collect();

        if ($loggedInUser->hasRole('admin')) {
            $filteredHierarchyLevels = $allHierarchyLevels;
        } elseif ($loggedInUser->hasRole('team')) {
            $currentUserHierarchyCode = $loggedInUser->hierarchy_level_code;
            $filteredHierarchyLevels = $allHierarchyLevels->filter(function ($level) use ($currentUserHierarchyCode, $allHierarchyLevels) {
                return $this->isHierarchyDescendantOrSame($currentUserHierarchyCode, $level->code, $allHierarchyLevels);
            });
        } elseif ($loggedInUser->hasRole('app_user')) {
            $filteredHierarchyLevels = $allHierarchyLevels->where('code', $loggedInUser->hierarchy_level_code);
        } else {
            $filteredHierarchyLevels = $allHierarchyLevels->where('code', $loggedInUser->hierarchy_level_code);
        }

        return $filteredHierarchyLevels;
    }

        /**
     * Check if one hierarchy is a descendant of another.
     */
    protected function isHierarchyDescendantOrSame(string $parentHierarchyCode, string $childHierarchyCode): bool
    {
        if ($parentHierarchyCode === $childHierarchyCode) {
            return true;
        }

        $allHierarchyLevels = HierarchyLevel::all()->keyBy('code');
        $current = $allHierarchyLevels->get($childHierarchyCode);

        while ($current) {
            if ($current->parent_code === $parentHierarchyCode) {
                return true;
            }
            $current = $allHierarchyLevels->get($current->parent_code);
        }

        return false;
    }

}