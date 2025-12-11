<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\HierarchyLevel;

class DashboardRbmController extends Controller
{
    /**
     * Halaman Landing Page
     */
    public function index()
    {
        $user = Auth::user();
        
        // Redirect jika user punya unit atau dia adalah admin
        if ($user->hierarchy_level_code || $user->hasRole('admin')) {
            return redirect()->route('executive.monitoring_rbm');
        }

        return view('executive.dashboard');
    }

    /**
     * Dashboard Monitoring RBM
     */
    public function monitoring(Request $request)
    {
        $user = Auth::user();

        // 1. LOGIKA PENENTUAN AKSES (FILTER)
        $filter = null;

        // A. CEK ADMIN (BYPASS LANGSUNG DI SINI AGAR ANTI-GAGAL)
        // Kita gunakan DB::raw('1') agar query menjadi "WHERE 1 = 1" (Ambil Semua Data)
        if ($user->hasRole('admin')) {
            $filter = ['column' => DB::raw('1'), 'code' => 1];
        } 
        // B. JIKA BUKAN ADMIN, GUNAKAN HELPER HIRARKI
        else {
            $filter = $this->getHierarchyFilterForJoin($user);
        }

        // Jika filter masih null (artinya bukan Admin DAN tidak punya Unit Code), baru tolak.
        if (!$filter) {
            abort(403, 'Anda tidak memiliki akses ke Unit Layanan manapun.');
        }

        $column = $filter['column']; 
        $unitCode = $filter['code'];

        // Tampilan Judul Unit (Untuk Admin jadi 'GLOBAL')
        $displayUnit = $user->hasRole('admin') ? 'GLOBAL (SEMUA UNIT)' : $unitCode;

        // =================================================================================
        // LOGIKA STATISTIK (QUERY DATA)
        // =================================================================================

        // A. STATISTIK UTAMA
        $stats = [
            'total_plg' => DB::table('master_data_pelanggan')
                ->where($column, $unitCode)
                ->count(),
            
            'sudah_petakan' => DB::table('mapping_kddk')
                ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($column, $unitCode)
                ->where('mapping_kddk.enabled', 1)
                ->whereNotNull('mapping_kddk.kddk')
                ->count(),
            
            'tanpa_coord' => DB::table('mapping_kddk')
                ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($column, $unitCode)
                ->where('mapping_kddk.enabled', 1)
                ->where(function($q) {
                    $q->whereNull('latitudey')->orWhere('latitudey', '0');
                })
                ->count(),

            'total_rute' => DB::table('mapping_kddk')
                ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($column, $unitCode)
                ->where('mapping_kddk.enabled', 1)
                ->distinct()
                ->select(DB::raw('SUBSTRING(mapping_kddk.kddk, 1, 7) as rute_code'))
                ->count('mapping_kddk.kddk')
        ];

        $stats['progress_persen'] = $stats['total_plg'] > 0 
            ? round(($stats['sudah_petakan'] / $stats['total_plg']) * 100, 1) 
            : 0;

        // B. GRAFIK PER AREA
        $areaStats = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->selectRaw('SUBSTRING(mapping_kddk.kddk, 4, 2) as area_code, COUNT(*) as total')
            ->where($column, $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->whereNotNull('mapping_kddk.kddk')
            ->groupBy('area_code')
            ->orderBy('area_code')
            ->get();

        // C. GRAFIK KUALITAS
        $validCoord = $stats['sudah_petakan'] - $stats['tanpa_coord'];
        $qualityStats = [
            'valid' => $validCoord,
            'invalid' => $stats['tanpa_coord']
        ];

        // D. TOP ROUTES
        $topRoutes = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->selectRaw('SUBSTRING(mapping_kddk.kddk, 1, 7) as rute, COUNT(*) as total')
            ->where($column, $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->groupBy('rute')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Kirim $unitCode sebagai $displayUnit agar Admin melihat teks 'GLOBAL'
        $viewData = [
            'unitCode' => $displayUnit, 
            'stats' => $stats, 
            'areaStats' => $areaStats, 
            'qualityStats' => $qualityStats, 
            'topRoutes' => $topRoutes
        ];

        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.dashboard_content', $viewData);
        }
        return view('team.matrix_kddk.dashboard', $viewData);
    }

    /**
     * Helper: Menentukan Kolom Filter
     */
    private function getHierarchyFilterForJoin($user): ?array
    {
        // Fallback check di helper juga
        if ($user->hasRole('admin')) {
            return ['column' => DB::raw('1'), 'code' => 1];
        }

        $userHierarchyCode = $user->hierarchy_level_code;
        
        if (!$userHierarchyCode) return null; 
        
        $level = HierarchyLevel::where('code', $userHierarchyCode)->with('parent.parent')->first();
        if (!$level) return null;

        // Level UID
        if ($level->parent_code === null) {
            return ['column' => 'master_data_pelanggan.unitupi', 'code' => $userHierarchyCode];
        } 
        // Level UP3
        elseif ($level->parent && $level->parent->parent_code === null) {
            return ['column' => 'master_data_pelanggan.unitap', 'code' => $userHierarchyCode];
        }
        // Level ULP
        return ['column' => 'master_data_pelanggan.unitup', 'code' => $userHierarchyCode];
    }
}