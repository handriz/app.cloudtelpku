<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB; // <--- WAJIB: Tambahkan ini untuk query DB
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Menampilkan semua pengaturan (Index)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $scopeCode = $user->hasRole('admin') ? null : $user->hierarchy_level_code;

        // 1. Ambil Konfigurasi
        $kddkConfig = AppSetting::findValue('kddk_config_data', $scopeCode, $this->getDefaultKddkConfig());
        $activePeriod = AppSetting::findValue('kddk_active_period', $scopeCode, date('Y-m'));

        // 2. Susun Collection
        $settings = collect([
            (object)['key' => 'kddk_active_period', 'label' => 'Periode Data Aktif', 'value' => $activePeriod, 'type' => 'string', 'group' => 'general'],
            (object)['key' => 'kddk_config_data', 'label' => 'Konfigurasi Area & Rute', 'value' => $kddkConfig, 'type' => 'json', 'group' => 'kddk']
        ])->groupBy('group');

        $currentScope = $user->hasRole('admin') ? 'global' : 'local';
        $viewData = compact('settings', 'currentScope', 'scopeCode', 'kddkConfig');

        if ($request->has('is_ajax')) {
            return view('admin.settings.partials.index_content', $viewData);
        }
        return view('admin.settings.index', $viewData);
    }

    /**
     * Menambah Area Baru ke JSON Config
     */
    public function addArea(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:2|regex:/^[A-Z0-9]+$/', // Izinkan angka juga jika perlu
            'label' => 'required|string|max:50',
            'category' => 'nullable|string|max:30', // <--- INPUT BARU
        ]);

        $newCode = strtoupper($request->code);

        // 1. Ambil Data Lama
        $user = Auth::user();
        $scope = $user->hasRole('admin') ? null : $user->hierarchy_level_code;
        $config = \App\Models\AppSetting::findValue('kddk_config_data', $scope, $this->getDefaultKddkConfig());

        $areas = collect($config['areas'] ?? []);

        // 2. Validasi Duplikat
        if ($areas->contains('code', $newCode)) {
            return response()->json(['success' => false, 'message' => "Area dengan kode '$newCode' sudah ada."], 422);
        }

        // 3. Tambah Item Baru DENGAN KATEGORI
        $areas->push([
            'code' => $newCode,
            'label' => $request->label,
            'category' => $request->category ?? 'Umum', // Default 'Umum' jika kosong
            'routes' => []
        ]);

        // 4. Simpan
        // Sortir agar rapi: Kategori dulu, baru Kode Area
        $sortedAreas = $areas->sortBy(['category', 'code'])->values()->all();
        $config['areas'] = $sortedAreas;

        \App\Models\AppSetting::updateOrCreate(
            ['key' => 'kddk_config_data', 'hierarchy_code' => $scope],
            [
                'value' => json_encode($config),
                'type' => 'json',
                'group' => 'kddk',
                'label' => 'Konfigurasi Area & Rute'
            ]
        );

        return response()->json(['success' => true, 'message' => 'Area berhasil ditambahkan.']);
    }

    /**
     * Update Data Area (Label & Kategori)
     */
    public function updateArea(Request $request)
    {
        $request->validate([
            'code' => 'required|string', // Kode jadi kunci pencarian
            'label' => 'required|string|max:50',
            'category' => 'nullable|string|max:30',
        ]);

        $targetCode = strtoupper($request->code);

        // 1. Ambil Data
        $user = Auth::user();
        $scope = $user->hasRole('admin') ? null : $user->hierarchy_level_code;
        $config = \App\Models\AppSetting::findValue('kddk_config_data', $scope, $this->getDefaultKddkConfig());

        $areas = collect($config['areas'] ?? []);

        // 2. Cari & Update Item
        // Kita gunakan map untuk memodifikasi item di dalam collection
        $updatedAreas = $areas->map(function ($area) use ($targetCode, $request) {
            if ($area['code'] === $targetCode) {
                $area['label'] = $request->label;
                $area['category'] = $request->category ?? 'Umum';
            }
            return $area;
        });

        // 3. Simpan Kembali
        // Sortir ulang agar rapi (Kategori -> Kode)
        $config['areas'] = $updatedAreas->sortBy(['category', 'code'])->values()->all();

        \App\Models\AppSetting::updateOrCreate(
            ['key' => 'kddk_config_data', 'hierarchy_code' => $scope],
            [
                'value' => json_encode($config),
                'type' => 'json',
                'group' => 'kddk',
                'label' => 'Konfigurasi Area & Rute'
            ]
        );

        return response()->json(['success' => true, 'message' => 'Area berhasil diperbarui.']);
    }

    public function manageRoutes(Request $request, $areaCode)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        
        $userCode = $user->hierarchy_level_code; 
        $configScope = $isAdmin ? null : $userCode;

        // Load Config
        $config = \App\Models\AppSetting::findValue('kddk_config_data', $configScope, []);
        if (empty($config) && !$isAdmin) {
            $config = \App\Models\AppSetting::findValue('kddk_config_data', null, []);
        }

        $areasCollection = collect($config['areas'] ?? []);
        $targetArea = $areasCollection->firstWhere('code', $areaCode);
        $routesConfig = $targetArea['routes'] ?? []; 
        
        // 2. TENTUKAN PREFIX UNIT (KEAMANAN KETAT)
        if ($isAdmin) {
             // Admin boleh lihat semua (3 digit apa saja)
            $officialPrefix = '___';
        } else {
            // User Biasa: DEFAULT MATI ('###'). 
            // Jangan pakai '___', nanti user baru bisa lihat data unit lain!
            $officialPrefix = '###'; 

            if ($userCode) {
                $hierarchy = \App\Models\HierarchyLevel::where('code', $userCode)->first();
                
                if ($hierarchy) {
                    if ($hierarchy->unit_type === 'ULP') {
                        // Pola: UP3 + ULP + Wildcard Sub
                        $ulpCode = $hierarchy->kddk_code; 
                        $up3Code = $hierarchy->parent ? $hierarchy->parent->kddk_code : '_'; 
                        
                        // Pastikan kode valid (A-Z)
                        if ($ulpCode && $up3Code) {
                            $officialPrefix = $up3Code . $ulpCode . '_';
                        }
                    } 
                    elseif ($hierarchy->unit_type === 'UP3') {
                        $up3Code = $hierarchy->kddk_code;
                        if ($up3Code) {
                            $officialPrefix = $up3Code . '__';
                        }
                    }
                    elseif ($hierarchy->unit_type === 'SUB_ULP') {
                        $subCode = $hierarchy->kddk_code;
                        $ulpCode = $hierarchy->parent ? $hierarchy->parent->kddk_code : '_';
                        $up3Code = ($hierarchy->parent && $hierarchy->parent->parent) ? $hierarchy->parent->parent->kddk_code : '_';
                        $officialPrefix = $up3Code . $ulpCode . $subCode;
                    }
                }
            }
        }

        // 3. HITUNG PELANGGAN (TANPA DETEKTIF / STRICT MODE)
        $routes = collect($routesConfig)->map(function ($route, $index) use ($areaCode, $officialPrefix) {
            
            // Cari HANYA dengan Prefix Unit Resmi
            // Jika user 18120 belum disetting hirarkinya, prefixnya '###'
            // Maka query LIKE '###AAAB%' -> Hasil pasti 0 (Aman)
            $strictPrefix = $officialPrefix . $areaCode . $route['code'];
            
            $count = \Illuminate\Support\Facades\DB::table('mapping_kddk')
                ->where('kddk', 'like', $strictPrefix . '%')
                ->count();

            $route['customer_count'] = $count;
            $route['original_index'] = $index;
            
            // Hapus label warning detektif jika ada sisa
            // $route['label'] tetap murni
            
            return $route;

        })->sortBy('code');

        $groupedRoutes = $routes->groupBy(function ($item) {
            return substr($item['code'], 0, 1);
        });

        $viewData = compact('areaCode', 'routes', 'groupedRoutes');

        if ($request->has('is_ajax')) {
            return view('admin.settings.partials.routes_manage_content', $viewData);
        }

        // 2. Jika Akses Langsung/Refresh/Fallback, kembalikan Halaman Utuh (Wrapper)
        return view('admin.settings.manage_routes_wrapper', $viewData);
    }

    /**
     * Update Pengaturan (Handle Global Update & Partial Route Update)
     */
    public function update(Request $request)
    {
        // Deteksi apakah ini update rute spesifik (Partial Update)
        $targetAreaCode = $request->input('area_code_target');

        // 1. VALIDASI DINAMIS
        if ($targetAreaCode) {
            // --- Validasi Mode: MANAGE RUTE ---
            $request->validate([
                "settings.kddk_config_data.routes_manage.{$targetAreaCode}.*.code" => 'required|string|size:2|regex:/^[A-Z]{2}$/|distinct',
                "settings.kddk_config_data.routes_manage.{$targetAreaCode}.*.label" => 'required|string|max:100',
            ], [
                // --- PESAN ERROR KUSTOM (Agar Mudah Dibaca) ---
                // Gunakan wildcard (*) untuk menangkap semua index array

                // Error Duplikat
                'settings.kddk_config_data.routes_manage.*.*.code.distinct' => 'Terdapat Kode Rute yang ganda (Duplikat). Mohon cek inputan Anda.',

                // Error Format (Bukan Huruf)
                'settings.kddk_config_data.routes_manage.*.*.code.regex' => 'Kode Rute wajib 2 Huruf Kapital (A-Z).',

                // Error Wajib Isi
                'settings.kddk_config_data.routes_manage.*.*.code.required' => 'Kode Rute wajib diisi.',
                'settings.kddk_config_data.routes_manage.*.*.label.required' => 'Keterangan Rute wajib diisi.',

                // Error Panjang Karakter
                'settings.kddk_config_data.routes_manage.*.*.code.size' => 'Kode Rute harus tepat 2 karakter.',
            ]);
        } else {
            // --- Validasi Mode: UTAMA (Index) ---
            $request->validate([
                'settings.kddk_config_data.areas.*.code' => 'required|string|size:2|regex:/^[A-Z]{2}$/|distinct',
                'settings.kddk_config_data.areas.*.label' => 'required|string|max:100',
                'settings.kddk_config_data.route_format' => 'required|in:ALPHA',
            ], [
                'settings.kddk_config_data.areas.*.code.regex' => 'Kode Area wajib 2 Huruf Kapital.',
                'settings.kddk_config_data.areas.*.code.distinct' => 'Kode Area tidak boleh ganda.',
            ]);
        }

        $user = Auth::user();
        $targetHierarchy = $user->hasRole('admin') ? null : $user->hierarchy_level_code;
        $inputs = $request->input('settings', []);

        foreach ($inputs as $key => $value) {

            // Auto-create definition jika belum ada
            $settingDefinition = AppSetting::firstOrCreate(
                ['key' => $key, 'hierarchy_code' => null],
                [
                    'type' => ($key === 'kddk_config_data') ? 'json' : 'string',
                    'group' => ($key === 'kddk_config_data') ? 'kddk' : 'general',
                    'label' => 'Konfigurasi ' . $key,
                    'value' => ($key === 'kddk_config_data') ? json_encode($this->getDefaultKddkConfig()) : null,
                ]
            );

            $saveValue = $value;
            $type = $settingDefinition->type;

            if ($type === 'json' && $key === 'kddk_config_data') {

                // KASUS A: Update Partial Rute (Dari Halaman Manage Routes)
                if ($targetAreaCode && isset($value['routes_manage'])) {
                    // 1. Ambil data eksisting dari DB
                    $currentData = AppSetting::findValue('kddk_config_data', $targetHierarchy, $this->getDefaultKddkConfig());

                    // 2. Update area spesifik
                    if (isset($currentData['areas'])) {
                        foreach ($currentData['areas'] as &$area) {
                            if ($area['code'] === $targetAreaCode) {
                                $newRoutes = $value['routes_manage'][$targetAreaCode] ?? [];
                                // Format & Uppercase
                                $area['routes'] = collect($newRoutes)->map(function ($r) {
                                    $r['code'] = strtoupper($r['code']);
                                    return $r;
                                })->values()->all();
                                break;
                            }
                        }
                    }
                    $saveValue = json_encode($currentData);
                }
                // KASUS B: Update Master Area (Dari Halaman Index)
                else if (isset($value['areas'])) {
                    // Ambil data lama untuk mempertahankan rute jika tidak dikirim
                    $oldData = AppSetting::findValue('kddk_config_data', $targetHierarchy, $this->getDefaultKddkConfig());
                    $oldAreas = collect($oldData['areas'] ?? [])->keyBy('code');

                    $value['areas'] = collect($value['areas'])->map(function ($area) use ($oldAreas) {
                        $area['code'] = strtoupper($area['code']);
                        // Pertahankan rute lama jika area code sama
                        if ($oldAreas->has($area['code'])) {
                            $area['routes'] = $oldAreas->get($area['code'])['routes'] ?? [];
                        } else {
                            $area['routes'] = [];
                        }
                        return $area;
                    })->values()->all();

                    $saveValue = json_encode($value);
                }
            }

            AppSetting::updateOrCreate(
                ['key' => $key, 'hierarchy_code' => $targetHierarchy],
                [
                    'value' => $saveValue,
                    'type' => $type,
                    'group' => $settingDefinition->group,
                    'label' => $settingDefinition->label,
                ]
            );
        }

        Cache::flush();
        return response()->json(['success' => true, 'message' => 'Pengaturan berhasil disimpan.']);
    }

    /**
     * METHOD HAPUS ITEM (Area / Rute) dengan Validasi
     */
    public function deleteKddkConfigItem(Request $request)
    {
        $request->validate([
            'type' => 'required|in:area,route',
            'area_code' => 'required|string',
            'route_code' => 'nullable|string',
        ]);

        $type = $request->type;
        $targetAreaCode = $request->area_code;
        $targetRouteCode = $request->route_code;

        // 1. VALIDASI DATABASE (Integritas Data)
        if ($type === 'area') {
            // Cek apakah ada pelanggan dengan kode area ini (Digit 4-5)
            // Pola: 3 sembarang + AA (Area) + ...
            $exists = DB::table('mapping_kddk')
                ->where('kddk', 'like', '___' . $targetAreaCode . '%')
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Gagal! Area '{$targetAreaCode}' sedang digunakan oleh data pelanggan."
                ], 422);
            }
        } elseif ($type === 'route') {
            // Cek apakah ada pelanggan dengan rute ini (Digit 4-7)
            // Pola: 3 sembarang + AA (Area) + BB (Rute) + ...
            $prefix = '___' . $targetAreaCode . $targetRouteCode;
            $exists = DB::table('mapping_kddk')
                ->where('kddk', 'like', $prefix . '%')
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Gagal! Rute '{$targetRouteCode}' di Area '{$targetAreaCode}' sedang digunakan oleh data pelanggan."
                ], 422);
            }
        }

        // 2. HAPUS DARI CONFIG (JSON)
        $user = Auth::user();
        $targetHierarchy = $user->hasRole('admin') ? null : $user->hierarchy_level_code;

        $kddkConfig = AppSetting::findValue('kddk_config_data', $targetHierarchy, $this->getDefaultKddkConfig());
        $areas = collect($kddkConfig['areas'] ?? []);

        if ($type === 'area') {
            // Cek apakah area punya rute? (Validasi tambahan)
            $targetArea = $areas->firstWhere('code', $targetAreaCode);
            if ($targetArea && !empty($targetArea['routes'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Gagal! Area '{$targetAreaCode}' masih memiliki Rute. Hapus semua rute terlebih dahulu."
                ], 422);
            }

            // Hapus Area
            $areas = $areas->reject(function ($area) use ($targetAreaCode) {
                return $area['code'] === $targetAreaCode;
            });
        } elseif ($type === 'route') {
            // Hapus Rute spesifik
            $areas = $areas->map(function ($area) use ($targetAreaCode, $targetRouteCode) {
                if ($area['code'] === $targetAreaCode && isset($area['routes'])) {
                    $area['routes'] = collect($area['routes'])->reject(function ($route) use ($targetRouteCode) {
                        return $route['code'] === $targetRouteCode;
                    })->values()->all();
                }
                return $area;
            });
        }

        // Simpan Perubahan
        $kddkConfig['areas'] = $areas->values()->all();

        AppSetting::updateOrCreate(
            ['key' => 'kddk_config_data', 'hierarchy_code' => $targetHierarchy],
            [
                'value' => json_encode($kddkConfig),
                'type' => 'json',
                'group' => 'kddk',
                'label' => 'Konfigurasi Area & Rute'
            ]
        );

        Cache::flush();

        return response()->json(['success' => true, 'message' => 'Item berhasil dihapus.']);
    }

    /**
     * Membersihkan Audit Log (Maintenance)
     */
    public function clearAuditLogs(Request $request)
    {
        // Hanya Admin yang boleh
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $days = (int) $request->input('retention_days', 60);
        $mode = $request->input('mode', 'old'); // 'old' atau 'all'

        try {
            if ($mode === 'all') {
                // HAPUS SEMUA (TRUNCATE)
                \App\Models\AuditLog::truncate();
                $count = 'Semua';
            } else {
                // HAPUS YANG LEBIH TUA DARI X HARI
                $date = now()->subDays($days);
                $query = \App\Models\AuditLog::where('created_at', '<', $date);
                $count = $query->count();
                $query->delete();
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil membersihkan {$count} data riwayat aktivitas."
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Default configuration data for KDDK (Fallback jika DB kosong)
     */
    private function getDefaultKddkConfig()
    {
        return [
            'areas' => [
                ['code' => 'AA', 'label' => 'RBM (Paskabayar)', 'routes' => []],
                ['code' => 'AB', 'label' => 'RPP (Prabayar)', 'routes' => []]
            ],
            'route_format' => 'ALPHA'
        ];
    }

    /**
     * Menangani Auto-Save dari Javascript
     */
    public function saveGenericSetting(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'key' => 'required|string',
            'group' => 'required|string',
        ]);

        $user = Auth::user();
        // Cek level user (Admin Global atau Unit)
        $scopeCode = $user->hasRole('admin') ? null : $user->hierarchy_level_code;

        // 2. Deteksi Tipe Data Otomatis (REVISI PRESIISI KOORDINAT)
        $value = $request->value;
        $type = 'string';

        if (is_numeric($value)) {
            // Cek apakah angka ini punya desimal (titik)
            if (strpos((string)$value, '.') !== false) {
                $type = 'string'; // Simpan sbg String agar desimal (0.900) tidak hilang
            } else {
                $type = 'integer'; // Angka bulat murni (misal: 5000)
            }
        } elseif (is_bool($value) || $value === 'true' || $value === 'false') {
            $type = 'boolean';
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        } elseif (is_array($value)) {
            $type = 'json';
            $value = json_encode($value);
        }

        // 3. Simpan ke Database (Atomic Update)
        try {
            AppSetting::updateOrCreate(
                [
                    'key' => $request->key,
                    'hierarchy_code' => $scopeCode
                ],
                [
                    'value' => $value,
                    'type' => $type,
                    'group' => $request->group,
                    'label' => $request->label ?? ucwords(str_replace('_', ' ', $request->key)),
                    'updated_by' => $user->id
                ]
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * [BARU] Cek apakah Area aman untuk dihapus?
     * Dipanggil via AJAX sebelum tombol hapus dieksekusi.
     */
    public function checkAreaUsage(Request $request, $areaCode)
    {
        // 1. Tentukan Prefix Area
        // Area di DB Mapping biasanya menempati digit ke-4 dan 5 (setelah 3 digit unit)
        // Pola: ___[KodeArea]%
        
        $prefix = '___' . $areaCode; 

        // 2. Hitung Pelanggan Aktif
        $count = \Illuminate\Support\Facades\DB::table('mapping_kddk')
            ->where('kddk', 'like', $prefix . '%')
            ->where('enabled', 1)
            ->count();

        // 3. Respon
        if ($count > 0) {
            return response()->json([
                'safe' => false,
                'count' => $count,
                'message' => "Gagal! Masih ada {$count} pelanggan aktif di Area {$areaCode}. Harap pindahkan atau hapus data pelanggan terlebih dahulu."
            ]);
        }

        return response()->json([
            'safe' => true,
            'message' => 'Area aman dihapus.'
        ]);
    }
}
