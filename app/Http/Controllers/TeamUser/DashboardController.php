<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterDataPelanggan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class DashboardController extends Controller
{
    public function index(Request $request)
    {

        $rekapData = MasterDataPelanggan::select('JENISLAYANAN', 'DAYA', DB::raw('count(*) as count'))
            ->groupBy('JENISLAYANAN', 'DAYA')
            ->orderBy('DAYA')
            ->get();

        $latestBulanRekap = MasterDataPelanggan::max('V_BULAN_REKAP');
        $totalPelanggan = MasterDataPelanggan::where('STATUS_DIL', 'AKTIF')->count();
        $distribusilayanan = $rekapData->groupBy('JENISLAYANAN')->map->sum('count');
        $pelangganPrabayarByDaya = $rekapData->where('JENISLAYANAN', 'PRABAYAR')->pluck('count', 'DAYA');
        $pelangganPaskabayarByDaya = $rekapData->where('JENISLAYANAN', 'PASKABAYAR')->pluck('count', 'DAYA');


        // Pengaturan sorting
        $sortableColumns = ['DAYA', 'total_pelanggan'];
        $sortColumn = $request->input('sort', 'DAYA');
        $sortDirection = $request->input('direction', 'asc');
        if (!in_array($sortColumn, $sortableColumns)) {
            $sortColumn = 'DAYA';
        }

        $query = MasterDataPelanggan::query() // Menggunakan Eloquent, scope otomatis berlaku
            ->select('DAYA', DB::raw('count(*) as total_pelanggan'))
            ->groupBy('DAYA');

        if ($sortColumn === 'DAYA') {
            $query->orderByRaw('CAST(DAYA AS UNSIGNED) ' . $sortDirection);
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $pelangganByDaya = $query->paginate(10)->withQueryString();

        $viewData = compact(
            'totalPelanggan','distribusilayanan','pelangganByDaya',
            'pelangganPrabayarByDaya','pelangganPaskabayarByDaya',
            'latestBulanRekap'
        );
        
        if ($request->has('is_ajax')) {
            return view('team.dashboard.partials.index_content', $viewData);
        }
        return view('team.dashboard.index',$viewData); 
    }
}

