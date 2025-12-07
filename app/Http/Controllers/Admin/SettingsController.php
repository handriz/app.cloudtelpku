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
     * Halaman Manage Routes (Detail)
     */
    public function manageRoutes(Request $request, $areaCode)
    {
        $user = Auth::user();
        $scopeCode = $user->hasRole('admin') ? null : $user->hierarchy_level_code;
        
        $kddkConfig = AppSetting::findValue('kddk_config_data', $scopeCode, $this->getDefaultKddkConfig());
        
        $currentArea = collect($kddkConfig['areas'] ?? [])->firstWhere('code', $areaCode);
        
        if (!$currentArea) {
             // Jika error, lebih baik redirect atau tampilkan error page dengan layout
             if (!$request->has('is_ajax')) return redirect()->route('admin.settings.index')->with('error', 'Kode Area tidak ditemukan.');
             return response()->json(['error' => 'Kode Area tidak ditemukan.'], 404);
        }

        $routesConfig = $currentArea['routes'] ?? [];

        $routes = collect($routesConfig)->map(function($route,$index) use ($areaCode) {
            // Pola KDDK: 3 digit awal (Hirarki) + 2 digit Area + 2 digit Rute + ...
            // Contoh: '___' + 'RB' + 'AA' + '%'
            $prefix = '___' . $areaCode . $route['code'];
            $count = \Illuminate\Support\Facades\DB::table('mapping_kddk')
                        ->where('kddk', 'like', $prefix . '%')
                        ->count();
            
            $route['customer_count'] = $count;
            $route['original_index'] = $index;
            return $route;
        });

        // Contoh: 'AA', 'AB' -> Masuk Group 'A'
        $groupedRoutes = $routes->sortBy('code')->groupBy(function($item) {
            return substr($item['code'], 0, 1); 
        });

        $viewData = compact('areaCode', 'routes', 'groupedRoutes','kddkConfig', 'scopeCode');

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
                                $area['routes'] = collect($newRoutes)->map(function($r) {
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
        } 
        elseif ($type === 'route') {
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
        } 
        elseif ($type === 'route') {
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
     * Default configuration data for KDDK (Fallback jika DB kosong)
     */
    private function getDefaultKddkConfig() {
        return [
            'areas' => [
                ['code' => 'AA', 'label' => 'RBM (Paskabayar)', 'routes' => []],
                ['code' => 'AB', 'label' => 'RPP (Prabayar)', 'routes' => []]
            ],
            'route_format' => 'ALPHA'
        ];
    }
}