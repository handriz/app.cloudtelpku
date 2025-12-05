<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $menuItems = MenuItem::with('children') // Eager load children untuk tampilan hierarki
                             ->whereNull('parent_id') // Hanya ambil menu level 1
                             ->where('is_active', true) // Hanya menu yang aktif
                             ->orderBy('order')
                             ->get();

        // Mengambil semua izin untuk dropdown saat menambah/mengedit menu
        $permissions = Permission::all();
        $viewData = compact('menuItems', 'permissions');

        if ($request->has('is_ajax')) {
            // Jika AJAX, kembalikan HANYA partial 'index_content'
            return view('admin.menu.partials.index_content', $viewData);
        }
        return view('admin.menu.index', $viewData);
    }

    public function create()
    {
        $parentMenus = MenuItem::whereNull('parent_id')->orderBy('order')->get(); // Untuk dropdown parent
        $permissions = Permission::all(); // Untuk dropdown permission
        return view('admin.menu.partials.create', compact('parentMenus', 'permissions'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'permission_name' => 'nullable|string|exists:permissions,name', // Validasi nama izin harus ada di tabel permissions
            'parent_id' => 'nullable|exists:menu_items,id',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {

            $data = $request->except('is_active');
            $data['is_active'] = $request->has('is_active');

            MenuItem::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Menu item berhasil ditambahkan!'
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal menyimpan menu: " . $e->getMessage());
            return response()->json(['errors' => ['server' => ['Gagal menyimpan data ke database.']]], 500);
        }
    }

    public function edit(MenuItem $menu)
    {
        $parentMenus = MenuItem::whereNull('parent_id')
                                ->where('id', '!=', $menu->id) // Hindari memilih diri sendiri sebagai parent
                                ->orderBy('order')
                                ->get();
        $permissions = Permission::all();
        return view('admin.menu.partials.edit', compact('menu', 'parentMenus', 'permissions'));
    }

   public function update(Request $request, MenuItem $menu)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'route_name' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'permission_name' => 'nullable|string|exists:permissions,name',
            'parent_id' => 'nullable|exists:menu_items,id',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        try {
            $data = $request->except('is_active');
            $data['is_active'] = $request->has('is_active');

            $menu->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Menu item berhasil diperbarui!'
            ]);
        
        }catch (\Exception $e) {
            Log::error("Gagal memperbarui menu: " . $e->getMessage());
            return response()->json(['errors' => ['server' => ['Gagal memperbarui data.']]], 500);
        }
    }

    public function destroy(MenuItem $menuItem)
    {
        // Saat menghapus menu induk, semua sub-menu (children) akan ikut terhapus
        // karena onDelete('cascade') pada foreignId('parent_id') di migrasi.

        try {

            $menuItem->delete();

            return response()->json([
                'message' => 'Menu item berhasil dihapus!'
            ]);

        } catch (\Exception $e) {
            Log::error("Gagal menghapus menu: " . $e->getMessage());
            return response()->json(['message' => 'Gagal menghapus data.'], 500);
        }
    }
}
