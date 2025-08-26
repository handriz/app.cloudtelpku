<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\HierarchyLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
   public function index()
    {
        // Mengambil semua pengguna dengan eager loading relasi 'role'
        $users = User::with(['role', 'hierarchyLevel'])->orderBy('name')->paginate(10);
        // $users = User::with('role')->orderBy('name')->simplePaginate(10);

        // Mengambil semua peran untuk dropdown filter atau input form (jika diperlukan)
        $roles = Role::all();
        $hierarchyLevels = HierarchyLevel::all();

        return view('admin.users.index', compact('users', 'roles',));
    }

    public function create()
    {
        $roles = Role::all(); // Untuk dropdown pilihan peran
        $hierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get(); 
        return view('admin.users.create', compact('roles','hierarchyLevels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'hierarchy_level_code' => 'nullable|string|max:255|exists:hierarchy_levels,code',
            'dashboard_route_name' => 'nullable|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'is_approved' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'hierarchy_level_code' => $request->hierarchy_level_code,
            'dashboard_route_name' => $request->dashboard_route_name, 
            'role_id' => $request->role_id,
            'is_approved' => $request->boolean('is_approved'),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil ditambahkan!');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
         $hierarchyLevels = HierarchyLevel::orderBy('parent_code')->orderBy('order')->get(); 
        return view('admin.users.edit', compact('user', 'roles','hierarchyLevels'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role_id' => 'required|exists:roles,id', // Validasi peran dari daftar yang diperbolehkan
            'hierarchy_level_code' => 'nullable|string|max:255|exists:hierarchy_levels,code', 
            'dashboard_route_name' => 'nullable|string|max:255',
            'is_approved' => 'boolean', // Validasi boolean untuk checkbox
            'password' => 'nullable|string|min:8|confirmed', // Untuk reset password
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) { // Hanya update password jika diisi
            $user->password = Hash::make($request->password);
        }
        $user->role_id = $request->role_id;
        $user->hierarchy_level_code = $request->hierarchy_level_code;
        $user->dashboard_route_name = $request->dashboard_route_name;
        $user->is_approved = $request->boolean('is_approved');
        $user->save();
        
        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil diperbarui!');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus!');
    }
}
