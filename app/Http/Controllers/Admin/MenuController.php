<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuItem;
use App\Models\Permission;

class MenuController extends Controller
{
    public function index()
    {
        $menuItems = MenuItem::with('children') // Eager load children untuk tampilan hierarki
                             ->whereNull('parent_id') // Hanya ambil menu level 1
                             ->where('is_active', true) // Hanya menu yang aktif
                             ->orderBy('order')
                             ->get();

        // Mengambil semua izin untuk dropdown saat menambah/mengedit menu
        $permissions = Permission::all();
        return view('admin.menu.index', compact('menuItems', 'permissions'));
    }

    public function create()
    {
        $parentMenus = MenuItem::whereNull('parent_id')->orderBy('order')->get(); // Untuk dropdown parent
        $permissions = Permission::all(); // Untuk dropdown permission
        return view('admin.menu.create', compact('parentMenus', 'permissions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'permission_name' => 'nullable|string|exists:permissions,name', // Validasi nama izin harus ada di tabel permissions
            'parent_id' => 'nullable|exists:menu_items,id',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        MenuItem::create($request->all());

        return redirect()->route('admin.menu.index')->with('success', 'Menu item berhasil ditambahkan!');
    }

    public function edit(MenuItem $menu)
    {

        $parentMenus = MenuItem::whereNull('parent_id')
                                ->where('id', '!=', $menu->id) // Hindari memilih diri sendiri sebagai parent
                                ->orderBy('order')
                                ->get();
        $permissions = Permission::all();
        return view('admin.menu.edit', compact('menu', 'parentMenus', 'permissions'));
    }

   public function update(Request $request, MenuItem $menu)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'permission_name' => 'nullable|string|exists:permissions,name',
            'parent_id' => 'nullable|exists:menu_items,id',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $menu->update($request->all());

        return redirect()->route('admin.menu.index')->with('success', 'Menu item berhasil diperbarui!');
    }

    public function destroy(MenuItem $menuItem)
    {
        // Saat menghapus menu induk, semua sub-menu (children) akan ikut terhapus
        // karena onDelete('cascade') pada foreignId('parent_id') di migrasi.
        $menuItem->delete();

        return redirect()->route('admin.menu.index')->with('success', 'Menu item berhasil dihapus!');
    }
}
