<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Menampilkan halaman utama Pengaturan (List Area)
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
     * Menampilkan Halaman Kelola Rute (Detail per Area)
     */
    public function manageRoutes(Request $request, $areaCode)
    {
        $user = Auth::user();
        $scopeCode = $user->hasRole('admin') ? null : $user->hierarchy_level_code;
        
        $kddkConfig = AppSetting::findValue('kddk_config_data', $scopeCode, $this->getDefaultKddkConfig());
        
        // Filter Area
        $currentArea = collect($kddkConfig['areas'] ?? [])->firstWhere('code', $areaCode);
        
        if (!$currentArea) {
             return response()->json(['error' => 'Kode Area tidak ditemukan.'], 404);
        }

        $routes = $currentArea['routes'] ?? [];
        
        $viewData = compact('areaCode', 'routes', 'kddkConfig', 'scopeCode');

        // Kembalikan view khusus Rute
        return view('admin.settings.partials.routes_manage_content', $viewData);
    }
    
    /**
     * Update Pengaturan (Handle Global Update & Partial Route Update)
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $targetHierarchy = $user->hasRole('admin') ? null : $user->hierarchy_level_code;

        // Deteksi apakah ini update rute spesifik
        $targetAreaCode = $request->input('area_code_target');

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

    private function getDefaultKddkConfig() {
        return [
            'areas' => [
                ['code' => 'RB', 'label' => 'RBM (Paskabayar)', 'routes' => []],
                ['code' => 'RP', 'label' => 'RPP (Prabayar)', 'routes' => []]
            ],
            'route_format' => 'ALPHA'
        ];
    }
}