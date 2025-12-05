<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HierarchyLevel;
use Illuminate\Validation\Rule;

class HierarchyController extends Controller
{
    /**
     * Menampilkan daftar level hirarki dengan hierarki bersarang.
     */
    public function index(Request $request)
    {
        $hierarchies = HierarchyLevel::whereNull('parent_code')
                                        ->with('childrenRecursive')
                                        ->orderBy('order')
                                        ->paginate(15);

        $viewData = compact('hierarchies');

        if ($request->has('is_ajax')) {
            return view('admin.hierarchies.partials.index_content', $viewData);
        }

        return view('admin.hierarchies.index', $viewData);
    }

    /**
     * Menampilkan formulir untuk membuat level hirarki baru.
     */
    public function create(Request $request)
    {
        $parentHierarchyLevels = HierarchyLevel::orderBy('order')->get();

        if ($request->has('is_ajax')) {
            return view('admin.hierarchies.partials.create_content', compact('parentHierarchyLevels'));
        }

        return view('admin.hierarchies.create', compact('parentHierarchyLevels'));
    }

    /**
     * Menyimpan level hirarki baru ke database.
     */
    public function store(Request $request)
    {
        // 1. LOGIKA AUTO-CODE KHUSUS SUB_ULP
        if ($request->unit_type === 'SUB_ULP' && $request->parent_code) {
            $lastCode = HierarchyLevel::where('parent_code', $request->parent_code)
                                      ->where('unit_type', 'SUB_ULP')
                                      ->max('kddk_code');

            if ($lastCode) {
                // CEK BATAS: Jika sudah 'Z', tidak bisa nambah lagi
                if ($lastCode === 'Z') {
                    return response()->json([
                        'message' => 'Gagal! Kuota Kode Huruf (A-Z) untuk Sub Unit di bawah induk ini sudah habis.'
                    ], 422);
                }
                // Jika belum Z, lanjut ke huruf berikutnya
                $nextCode = chr(ord($lastCode) + 1);
            } else {
                // Jika belum punya saudara, mulai dari A
                $nextCode = 'A';
            }
            
            // Masukkan ke request
            $request->merge(['kddk_code' => $nextCode]);
        }

        $request->validate([
            'code' => 'required|string|max:255|unique:hierarchy_levels,code',
            'name' => 'required|string|max:255',
            'parent_code' => 'nullable|string|exists:hierarchy_levels,code',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'unit_type' => 'required|in:UID,UP3,ULP,SUB_ULP',

            'kddk_code' => [
                Rule::requiredIf(fn() => !in_array($request->unit_type, ['UID'])),
                'nullable',
                'string',
                'size:1', // Wajib 1 Karakter
                'regex:/^[A-Za-z]$/', // Wajib Huruf Besar A-Z
                // Validasi Unik: Dalam 1 level unit (misal ULP), huruf tidak boleh sama
                Rule::unique('hierarchy_levels', 'kddk_code')
                    ->where(function ($query) use ($request) {
                        return $query->where('unit_type', $request->unit_type)
                            ->where('parent_code', $request->parent_code);
                    })
            ],
        ], [
            'kddk_code.unique' => 'Kode Huruf ini sudah dipakai oleh unit lain di level yang sama.',
        ]);

        HierarchyLevel::create([
            'code' => $request->code,
            'name' => strtoupper($request->name),
            'parent_code' => $request->parent_code,
            'order' => $request->order,
            'is_active' => $request->has('is_active'),
            'unit_type' => $request->unit_type,
            'kddk_code' => ($request->unit_type === 'UID') ? null : strtoupper($request->kddk_code),
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Unit Layanan berhasil ditambahkan. Kode KDDK otomatis: ' . $request->kddk_code
        ]);
    }

    /**
     * Menampilkan formulir untuk mengedit level hirarki yang ada.
     */
    public function edit(Request $request, HierarchyLevel $hierarchy)   
    {

        $parentHierarchyLevels = HierarchyLevel::where('id', '!=', $hierarchy->id)
                                               ->orderBy('name')
                                               ->get(); 

        $viewData = compact('hierarchy', 'parentHierarchyLevels');
        
        if ($request->has('is_ajax')) {
            return view('admin.hierarchies.partials.edit_content', $viewData);
        }

        return view('admin.hierarchies.edit', $viewData);
    }

    /**
     * Memperbarui level hirarki yang ada di database.
     */
    public function update(Request $request, HierarchyLevel $hierarchy)
    {
        // Debugging: Pastikan ID terbaca (Jika masih error, uncomment baris bawah ini)
        // dd($hierarchy->id, $request->all());

        $request->validate([
            // 1. Validasi CODE
            'code' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('hierarchy_levels', 'code')->ignore($hierarchy->id)
            ],
            'name' => 'required|string|max:255',
            'parent_code' => 'nullable|string|exists:hierarchy_levels,code',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'unit_type' => 'required|in:UID,UP3,ULP,SUB_ULP',

            // 2. Validasi KODE KDDK
            'kddk_code' => [
                Rule::requiredIf(fn() => $request->unit_type !== 'UID'),
                'nullable',
                'size:1',
                'regex:/^[A-Za-z]$/', // Terima huruf besar/kecil
                
                // Validasi Unik Bersyarat
                Rule::unique('hierarchy_levels', 'kddk_code')
                    ->where(function ($query) use ($request) {
                        // Filter berdasarkan Type
                        $query->where('unit_type', $request->unit_type);

                        // Filter berdasarkan Parent
                        // Jika parent_code dikirim kosong, kita cek yang parent_code-nya NULL di DB
                        if (empty($request->parent_code)) {
                            $query->whereNull('parent_code');
                        } else {
                            $query->where('parent_code', $request->parent_code);
                        }
                    })
                    ->ignore($hierarchy->id) // <--- PASTIKAN INI
            ],
        ], [
            'kddk_code.unique' => 'Kode Huruf KDDK ini sudah dipakai oleh unit lain di bawah Induk yang sama.',
        ]);

        // Cek Circular Reference
        if ($request->parent_code == $hierarchy->code) {
            return response()->json(['errors' => ['parent_code' => ['Tidak bisa menjadi induk diri sendiri.']]], 422);
        }

        $hierarchy->update([
            'code' => $request->code,
            'name' => strtoupper($request->name),
            'parent_code' => $request->parent_code ?: null, // Pastikan string kosong jadi null
            'order' => $request->order,
            'is_active' => $request->has('is_active'),
            'unit_type' => $request->unit_type,
            'kddk_code' => ($request->unit_type === 'UID') ? null : strtoupper($request->kddk_code),
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Data Unit berhasil diperbarui!'
        ]);
    }

    /**
     * Menghapus level hirarki dari database.
     */
    public function destroy(HierarchyLevel $hierarchy) 
    {
        // Set parent_code dari anak-anak menjadi null sebelum menghapus induk
        $hasChildren = HierarchyLevel::where('parent_code', $hierarchy->code)->exists();

        if ($hasChildren) {
            return response()->json([
                'message' => 'Gagal! Unit ini memiliki bawahan (Sub Unit). Hapus atau pindahkan bawahan terlebih dahulu.'
            ], 422);
        }

        $hierarchy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit berhasil dihapus!'
        ]);
    }
}