<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\HierarchyLevel;

class MatrixKddkController extends Controller
{
    /**
     * Halaman Utama Matrix KDDK (Support AJAX)
     */
    public function index(Request $request)
    {
        // 1. Logika Pengambilan Data Matrix (Contoh: Unit vs Status Validasi)
        // Anda bisa menyesuaikan query ini nanti sesuai kebutuhan spesifik
        $user = Auth::user();
        $hierarchyFilter = $this->getHierarchyFilterForJoin($user);

        $query = DB::table('master_data_pelanggan')
            ->leftJoin('temporary_mappings', 'master_data_pelanggan.idpel', '=', 'temporary_mappings.idpel')
            ->select(
                'master_data_pelanggan.unitup as unit_layanan',
                DB::raw('COUNT(*) as total_pelanggan'),
                DB::raw('COUNT(temporary_mappings.id) as sudah_di_mapping'),
                DB::raw('COUNT(CASE WHEN temporary_mappings.is_validated = 1 THEN 1 END) as sudah_valid'),
                DB::raw('COUNT(CASE WHEN temporary_mappings.ket_validasi LIKE "rejected_%" THEN 1 END) as ditolak')
            );

        // Terapkan filter hirarki jika bukan admin
        if (!$user->hasRole('admin') && $hierarchyFilter) {
            $query->where($hierarchyFilter['column'], $hierarchyFilter['code']);
        }

        $matrixData = $query->groupBy('master_data_pelanggan.unitup')
                            ->orderBy('unit_layanan')
                            ->get();

        $viewData = compact('matrixData');

        // 2. DETEKSI AJAX: Jika permintaan dari tab-manager.js
        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.index_content', $viewData);
        }

        // 3. Jika akses langsung (Full Page Load)
        return view('team.matrix_kddk.index', $viewData);
    }

    // Helper Filter Hirarki (Sama seperti di controller lain)
    private function getHierarchyFilterForJoin($user): ?array
    {
        if ($user->hasRole('admin')) return null;
        $userHierarchyCode = $user->hierarchy_level_code;
        if (!$userHierarchyCode) return ['column' => 'master_data_pelanggan.id', 'code' => -1];
        
        $level = HierarchyLevel::where('code', $userHierarchyCode)->with('parent.parent')->first();
        if (!$level) return ['column' => 'master_data_pelanggan.id', 'code' => -1];

        if ($level->parent_code === null) {
            return ['column' => 'master_data_pelanggan.unitupi', 'code' => $userHierarchyCode];
        } elseif ($level->parent && $level->parent->parent_code === null) {
            return ['column' => 'master_data_pelanggan.unitap', 'code' => $userHierarchyCode];
        }
        return ['column' => 'master_data_pelanggan.unitup', 'code' => $userHierarchyCode];
    }
}