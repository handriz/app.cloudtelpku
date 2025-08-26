<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HierarchyLevel;

class HierarchyController extends Controller
{
    /**
     * Menampilkan daftar level hirarki dengan hierarki bersarang.
     */
    public function index()
    {
        $hierarchyLevels = HierarchyLevel::with('children.children.children')
                                        ->whereNull('parent_code')
                                        ->orderBy('order')
                                        ->get();

        return view('admin.hierarchies.index', compact('hierarchyLevels'));
    }

    /**
     * Menampilkan formulir untuk membuat level hirarki baru.
     */
    public function create()
    {
        $parentHierarchyLevels = HierarchyLevel::orderBy('order')->get();
        return view('admin.hierarchies.create', compact('parentHierarchyLevels'));
    }

    /**
     * Menyimpan level hirarki baru ke database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255|unique:hierarchy_levels,code',
            'name' => 'required|string|max:255',
            'parent_code' => 'nullable|string|exists:hierarchy_levels,code',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        HierarchyLevel::create($request->all());

        return redirect()->route('admin.hierarchies.index')->with('success', 'Level hirarki berhasil ditambahkan!');
    }

    /**
     * Menampilkan formulir untuk mengedit level hirarki yang ada.
     */
    public function edit(HierarchyLevel $hierarchy) // Perubahan nama parameter menjadi $hierarchy
    {
        // Variabel $hierarchy sudah diisi secara otomatis oleh Laravel
        // Kita juga perlu semua level hirarki lain untuk dropdown parent
        $parentHierarchyLevels = HierarchyLevel::orderBy('order')->get(); 
        
        // Pastikan $hierarchy dikirim ke view
        return view('admin.hierarchies.edit', compact('hierarchy', 'parentHierarchyLevels'));
    }

    /**
     * Memperbarui level hirarki yang ada di database.
     */
    public function update(Request $request, HierarchyLevel $hierarchy) // Perubahan nama parameter menjadi $hierarchy
    {
        $request->validate([
            'code' => 'required|string|max:255|unique:hierarchy_levels,code,' . $hierarchy->id,
            'name' => 'required|string|max:255',
            'parent_code' => 'nullable|string|exists:hierarchy_levels,code',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($request->parent_code === $hierarchy->code) {
            return back()->withErrors(['parent_code' => 'Level hirarki tidak dapat menjadi induk dirinya sendiri.'])->withInput();
        }

        $hierarchy->update($request->all());

        return redirect()->route('admin.hierarchies.index')->with('success', 'Level hirarki berhasil diperbarui!');
    }

    /**
     * Menghapus level hirarki dari database.
     */
    public function destroy(HierarchyLevel $hierarchy) // Perubahan nama parameter menjadi $hierarchy
    {
        // Set parent_code dari anak-anak menjadi null sebelum menghapus induk
        HierarchyLevel::where('parent_code', $hierarchy->code)->update(['parent_code' => null]);
        $hierarchy->delete();

        return redirect()->route('admin.hierarchies.index')->with('success', 'Level hirarki berhasil dihapus!');
    }
}