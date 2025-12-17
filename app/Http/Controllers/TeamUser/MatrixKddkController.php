<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\HierarchyLevel;
use App\Models\MasterKddk;
use App\Models\AppSetting; 
use App\Models\User;
use App\Models\AuditLog;

class MatrixKddkController extends Controller
{
    /**
     * Halaman Utama Matrix KDDK (Rekapitulasi)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $activePeriod = class_exists(\App\Models\AppSetting::class) 
            ? \App\Models\AppSetting::findValue('kddk_active_period', $user->hierarchy_level_code, date('Y-m'))
            : date('Y-m');

        $cacheKey = 'matrix_index_' . $user->id . '_' . $activePeriod;
        $matrixData = Cache::remember($cacheKey, 600, function () use ($user, $activePeriod) {

            $hierarchyFilter = $this->getHierarchyFilterForJoin($user);

            $query = DB::table('master_data_pelanggan')
                ->join('hierarchy_levels as h_ulp', 'master_data_pelanggan.unitup', '=', 'h_ulp.code')
                ->leftJoin('hierarchy_levels as h_up3', 'h_ulp.parent_code', '=', 'h_up3.code')
                ->leftJoin('mapping_kddk', 'master_data_pelanggan.idpel', '=', 'mapping_kddk.idpel')
                ->leftJoin('temporary_mappings', function($join) use ($activePeriod) {
                    $join->on('master_data_pelanggan.idpel', '=', 'temporary_mappings.idpel')
                        ->whereRaw("DATE_FORMAT(temporary_mappings.created_at, '%Y-%m') = ?", [$activePeriod]);
                })         
                ->select(
                    'h_ulp.name as unit_layanan', 
                    'master_data_pelanggan.unitup as unit_code',
                    'h_ulp.kddk_code as kode_ulp',
                    'h_up3.name as unit_induk_name',
                    'h_up3.kddk_code as kode_up3',
                    'h_up3.order as order_up3', 
                    'h_ulp.order as order_ulp',

                    // TARGET (Total DIL)
                    DB::raw('COUNT(master_data_pelanggan.id) as target_pelanggan'),
                    // SUDAH KDDK (Progress Grouping) -> INI YANG JADI ACUAN PERSENTASE
                    DB::raw('COUNT(DISTINCT mapping_kddk.id) as sudah_kddk'),
                    // VALIDASI (Realisasi Lapangan)
                    DB::raw('COUNT(DISTINCT temporary_mappings.id) as realisasi_survey'),
                    DB::raw('COUNT(DISTINCT CASE WHEN temporary_mappings.is_validated = 1 THEN temporary_mappings.id END) as valid'),
                    DB::raw('COUNT(DISTINCT CASE WHEN temporary_mappings.ket_validasi LIKE "rejected_%" THEN temporary_mappings.id END) as ditolak')
                );

            
                if (!$user->hasRole('admin') && $hierarchyFilter) {
                $query->where($hierarchyFilter['column'], $hierarchyFilter['code']);
            }

            $rawMatrix = $query->groupBy(
                'h_ulp.name',           // Group by Nama (Kota Barat)
                'master_data_pelanggan.unitup', // Group by Kode (18111)
                'h_ulp.kddk_code', 
                'h_up3.name', 
                'h_up3.kddk_code',
                'h_up3.order', 
                'h_ulp.order'
            )
            ->orderBy('h_up3.order', 'asc')
            ->orderBy('h_ulp.order', 'asc')
            ->get();

            // Logika Grouping (ULP vs Admin)
            $isUserULP = false;
            if (!$user->hasRole('admin') && $user->hierarchy_level_code) {
                $userType = \App\Models\HierarchyLevel::where('code', $user->hierarchy_level_code)
                            ->value('unit_type');
                if ($userType === 'ULP') {
                    $isUserULP = true;
                }
            }

            return $rawMatrix->groupBy(function($item) use ($isUserULP) {
                if ($isUserULP) return $item->unit_layanan; 
                return $item->unit_induk_name ?? 'LAINNYA';
            });

        });

        $viewData = compact('matrixData', 'activePeriod');

        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.index_content', $viewData);
        }

        return view('team.matrix_kddk.index', $viewData);
    }

    /**
     * Drill Down: Detail Pelanggan dalam Unit
     */
    public function details(Request $request, $unit)
    {
        $unitCode = urldecode($unit); // Kode Unit (misal: 18111)
        $user = Auth::user();
        
        $activePeriod = class_exists(\App\Models\AppSetting::class) 
            ? \App\Models\AppSetting::findValue('kddk_active_period', $user->hierarchy_level_code, date('Y-m'))
            : date('Y-m');

        // 1. AMBIL KONFIGURASI KDDK (Area & Rute dari Settings)
        $kddkConfig = \App\Models\AppSetting::findValue('kddk_config_data', $user->hierarchy_level_code, []);

        // 2. LOGIKA HIRARKI OTOMATIS (Dgt 1-3)
        $currentUnit = \App\Models\HierarchyLevel::where('code', $unitCode)->first();
        
        $autoCodes = ['up3' => '_', 'ulp' => '_', 'sub' => 'A']; // Default Sub 'A'
        $subUnits = collect(); 

        if ($currentUnit) {
             if ($currentUnit->unit_type === 'ULP') {
                $autoCodes['ulp'] = $currentUnit->kddk_code;
                if ($currentUnit->parent) $autoCodes['up3'] = $currentUnit->parent->kddk_code;
                $subUnits = $currentUnit->children()->where('unit_type', 'SUB_ULP')->orderBy('kddk_code')->get();
                if ($subUnits->isNotEmpty()) $autoCodes['sub'] = ''; 
            } elseif ($currentUnit->unit_type === 'SUB_ULP') {
                $autoCodes['sub'] = $currentUnit->kddk_code;
                if ($currentUnit->parent) {
                    $autoCodes['ulp'] = $currentUnit->parent->kddk_code;
                    if ($currentUnit->parent->parent) $autoCodes['up3'] = $currentUnit->parent->parent->kddk_code;
                }
            }
        }

        // 3. QUERY DATA PELANGGAN
        $query = DB::table('master_data_pelanggan as mdp')
            // 1. Join untuk Status Validasi (Tidak mempengaruhi filtering utama)
            ->leftJoin('temporary_mappings as tm', 'mdp.idpel', '=', 'tm.idpel')
            
            // 2. Join ke Mapping KDDK Khusus yang ENABLED = 1 (Data Aktif)
            ->leftJoin('mapping_kddk as mk', function($join) {
                $join->on('mdp.idpel', '=', 'mk.idpel')
                     ->where('mk.enabled', 1);
            })

            ->select([
                'mdp.idpel',
                'mdp.nomor_meter_kwh',
                'mdp.merk_meter_kwh',
                'mdp.tarif',
                'mdp.status_dil',
                'mdp.daya',
                'mdp.unitup',
                'mdp.jenislayanan',

                'mk.kddk as current_kddk',
                'mk.user_pendataan',
                'mk.latitudey',
                'mk.longitudex',
                'mk.foto_kwh',
                'mk.foto_bangunan',
                'mk.namagd',

                'tm.is_validated',
                'tm.ket_validasi',
            ])

            ->where('mdp.unitup', $unitCode)

            ->where(function($q) {
                $q->whereNull('mk.kddk')
                  ->orWhere('mk.kddk', '');
            });
            
        // Filter Pencarian (Opsional)
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('mdp.idpel', 'like', $searchTerm)
                ->orWhere('mdp.nomor_meter_kwh', 'like', $searchTerm);
            });
        }

        $customers = $query
        ->orderBy('mdp.idpel')
        ->cursorPaginate(50)
        ->withPath(route('team.matrix_kddk.details', ['unit' => $unitCode]));

        $viewData = compact('customers', 'unit', 'activePeriod', 'autoCodes', 'subUnits', 'kddkConfig');

        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.detail_content', $viewData);
        }

        return view('team.matrix_kddk.details', $viewData);
    }

    public function validateUploadIds(Request $request)
    {
        $request->validate([
            'idpels' => 'required|array',
            'unitup' => 'required|string'
        ]);

        $rawIds = $request->idpels;
        $unitCode = $request->unitup;

        // Query Master Data + Cek Status Mapping
        $results = DB::table('master_data_pelanggan')
            ->leftJoin('mapping_kddk', 'master_data_pelanggan.idpel', '=', 'mapping_kddk.idpel')
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->whereIn('master_data_pelanggan.idpel', $rawIds)
            ->select(
                'master_data_pelanggan.idpel',
                'mapping_kddk.kddk'
            )
            ->get();

        $readyIds = [];   // Valid & Belum punya KDDK
        $mappedIds = [];  // Valid tapi SUDAH punya KDDK
        $foundList = [];  // Untuk tracking yg ketemu

        foreach ($results as $row) {
            $idStr = (string)$row->idpel;
            $foundList[] = $idStr;

            // Cek apakah kolom KDDK ada isinya
            if (!empty($row->kddk)) {
                $mappedIds[] = $idStr;
            } else {
                $readyIds[] = $idStr;
            }
        }

        // Hitung Statistik
        $totalSent = count($rawIds);
        $foundCount = count($results);
        $invalidCount = $totalSent - $foundCount; // Tidak ada di Master Data

        return response()->json([
            'ready_ids' => $readyIds,        // Ini yang akan kita seleksi
            'ready_count' => count($readyIds),
            
            'mapped_ids' => $mappedIds,      // Info saja
            'mapped_count' => count($mappedIds),
            
            'invalid_count' => $invalidCount,
            'total_received' => $totalSent
        ]);
    }

    /**
     * Halaman Kelola Matrix RBM (Assignment Petugas)
     */
    public function manageRbm(Request $request, $unit)
    {
        $unitCode = urldecode($unit);

        // 1. Data Hirarki (Header)
        $hierarchy = \App\Models\HierarchyLevel::where('code', $unitCode)->first();
        
        // 2. Ambil Data Pelanggan
        $rawCustomers = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->selectRaw('
                SUBSTRING(mapping_kddk.kddk, 4, 2) as area, 
                SUBSTRING(mapping_kddk.kddk, 6, 1) as digit6, 
                SUBSTRING(mapping_kddk.kddk, 7, 1) as digit7,
                COUNT(*) as total_pelanggan,
                MIN(mapping_kddk.kddk) as first_kddk,
                MIN(mapping_kddk.user_pendataan) as assigned_user
            ')
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->whereNotNull('mapping_kddk.kddk')
            ->whereRaw('LENGTH(mapping_kddk.kddk) >= 7') // Pastikan panjang minimal cukup
            ->groupBy('area', 'digit6', 'digit7')
            ->orderBy('area')
            ->orderBy('digit6')
            ->orderBy('digit7')
            ->get();

        // 3. Format: $groupedData[AREA][D6][D7] = ['count' => 15, 'first_kddk' => '...', 'assigned' => '...']
        $groupedData = [];
        foreach ($rawCustomers as $row) {
            $groupedData[$row->area][$row->digit6][$row->digit7] = [
                'count' => $row->total_pelanggan,
                'first_kddk' => $row->first_kddk,
                'user_id' => $row->assigned_user
            ];
        }

        // Config & Officers
        $user = Auth::user();
        $kddkConfig = \App\Models\AppSetting::findValue('kddk_config_data', auth()->user()->hierarchy_level_code, []);
        $areaLabels = collect($kddkConfig['areas'] ?? [])->pluck('label', 'code');
        $officers = \App\Models\User::whereHas('role', fn($q) => $q->where('name', 'appuser'))->get();

        // Ambil Base Prefix untuk display (UP3+ULP+Sub) dari hirarki
        $basePrefix = ($hierarchy->parent->kddk_code ?? '?') . ($hierarchy->kddk_code ?? '?') . 'A';

        $viewData = compact('unitCode', 'hierarchy', 'groupedData', 'officers', 'areaLabels', 'kddkConfig', 'basePrefix');
        

        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.rbm_manage_content', $viewData);
        }
        return view('team.matrix_kddk.rbm_manage', $viewData);
    }

    /**
     * [METODE BARU] API Lazy Load Tabel Pelanggan per Rute
     * Dipanggil via AJAX saat user klik Rute
     */
    public function getRouteTable(Request $request, $unit)
    {
        $unitCode = urldecode($unit);
        $area = $request->area;   // Misal: RB
        $route = $request->route; // Misal: A1 (Digit 6 + 7)

        // Query Detail Pelanggan (Hanya untuk rute ini)
        $data = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->select(
                'mapping_kddk.kddk',
                'master_data_pelanggan.idpel',
                'master_data_pelanggan.nomor_meter_kwh',
                'mapping_kddk.latitudey',
                'mapping_kddk.longitudex'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->where('mapping_kddk.kddk', 'like', '___' . $area . $route . '%')
            ->orderBy('mapping_kddk.kddk', 'asc')
            ->get();

        // Render Partial View (Hanya baris TR)
        return view('team.matrix_kddk.partials.route_table_rows', compact('data'))->render();
    }

    // --- 4. METHOD BARU: AJAX MAP DATA ---
    public function getMapData(Request $request, $unit)
    {
        $unitCode = urldecode($unit);
        $area = $request->area; // Filter Area
        $route = $request->route; // Filter Rute

        if (!$area) return response()->json([]);// Validasi: Kalau tidak ada Area, stop.

        $query = DB::table('mapping_kddk as mk')
            ->join('master_data_pelanggan as mdp', 'mk.idpel', '=', 'mdp.idpel')
            ->select(
                'mk.kddk', 'mk.latitudey', 'mk.longitudex', 
                'mk.idpel', 'mdp.nomor_meter_kwh'
            )
            ->where('mdp.unitup', $unitCode)
            ->where('mk.enabled', 1)
            ->whereNotNull('mk.latitudey')
            ->whereNotNull('mk.longitudex');

        if ($area) $query->where('mk.kddk', 'like', '___' . $area . '%');
        if ($route) $query->where('mk.kddk', 'like', '_____' . $route . '%');
        
        $data = $query->limit(10000)->get()->map(function($item) {
            $mapsUrl = "https://www.google.com/maps?q={$item->latitudey},{$item->longitudex}";
            return [
                'lat' => $item->latitudey,
                'lng' => $item->longitudex,
                'seq' => substr($item->kddk, 7, 3),
                'kddk' => $item->kddk,
                'idpel' => $item->idpel,
                'info' => "
                    <div class='text-xs font-sans'>
                        <div class='border-b border-gray-100 pb-1 mb-1'>
                            <strong class='text-indigo-600 block text-sm'>{$item->idpel}</strong>
                            <span class='text-gray-500 text-[10px]'>Urut: " . substr($item->kddk, 7, 3) . "</span>
                        </div>
                        
                        <div class='mb-2 text-gray-600'>
                            <div>Lat: <span class='font-mono'>{$item->latitudey}</span></div>
                            <div>Lon: <span class='font-mono'>{$item->longitudex}</span></div>
                        </div>

                        <a href='{$mapsUrl}' target='_blank' 
                           class='inline-flex items-center justify-center w-full px-2 py-1.5 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 rounded transition text-[10px] font-bold decoration-0'
                           style='text-decoration: none !important;'>
                            <i class='fas fa-map-marked-alt mr-1.5'></i> Buka Google Maps
                        </a>
                    </div>
                "
            ];
        });

        return response()->json($data);
    }
    
    /**
     * Export Data RBM ke CSV/Excel
     */
    public function exportRbm(Request $request, $unit)
    {
        $unitCode = urldecode($unit);
        $format = $request->query('format', 'excel'); // Default Excel
        $area = $request->area;
        $route = $request->route;
        $timestamp = now()->format('Ymd_His');
        
        // 1. Query Data
        $query = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->select(
                'mapping_kddk.kddk',
                'mapping_kddk.latitudey',
                'mapping_kddk.longitudex',
                'master_data_pelanggan.idpel',
                'master_data_pelanggan.tarif',
                'master_data_pelanggan.daya',
                'master_data_pelanggan.nomor_meter_kwh',
                'master_data_pelanggan.unitup'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->orderBy('mapping_kddk.kddk', 'asc');

        if ($area) {
            $query->where('mapping_kddk.kddk', 'like', '___' . $area . '%');
            // Tambahkan info ke nama file agar jelas
            $timestamp = $area . ($route ? "_{$route}_" : "_") . $timestamp;
        }
        
        if ($route) {
            $query->where('mapping_kddk.kddk', 'like', '_____' . $route . '%');
        }
        
        $data = $query->get();

        if ($data->isEmpty()) {
            return back()->with('error', 'Tidak ada data untuk diexport.');
        }

        // ==========================================
        // OPSI 1: EXPORT CSV (Pipa Delimiter)
        // ==========================================
        if ($format === 'csv') {
            $filename = "RBM_{$unitCode}_{$timestamp}.csv";
            
            // Header CSV (Sesuai request)
            $csvContent = "IDPEL|KDDK|LATITUDE|LONGITUDE\n";

            foreach ($data as $row) {
                // Pastikan KDDK 12 digit, Lat/Long tidak null
                $kddk = $row->kddk;
                $lat = $row->latitudey ?? '0';
                $long = $row->longitudex ?? '0';
                
                // Susun baris dengan pemisah pipa
                $csvContent .= "{$row->idpel}|{$kddk}|{$lat}|{$long}\n";
            }

            // Return text response sebagai file download
            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // ==========================================
        // OPSI 2: EXPORT EXCEL (Default HTML)
        // ==========================================
        $filename = "RBM_{$unitCode}_{$timestamp}.xls";
        return response(view('team.matrix_kddk.export_excel', compact('data', 'unitCode')), 200, [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Proses Pindah IDPEL ke KDDK Baru (Drag & Drop)
     */
    public function moveIdpelKddk(Request $request)
    {
        $request->validate([
            'idpel' => 'required|exists:mapping_kddk,idpel',
            'target_kddk' => 'required|string',
        ]);

        $idpel = $request->idpel;
        $target = $request->target_kddk;

        try {
            DB::transaction(function() use ($idpel, $target) {

                // 1. Ambil Kode Lama (Source Route) sebelum diupdate
                $oldKddk = DB::table('mapping_kddk')->where('idpel', $idpel)->value('kddk');
                $sourcePrefix = substr($oldKddk, 0, 7);
                
                // 2. Proses Pindah ke Target (Logika Lama)
                $finalKddk = $target;

                if (strlen($target) === 7) {
                    // Cari urutan terakhir di rute tujuan
                    $maxSeq = DB::table('mapping_kddk')
                        ->where('kddk', 'like', $target . '%')
                        ->max(DB::raw('CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED)'));
                    
                    $nextSeq = $maxSeq ? ($maxSeq + 1) : 1;
                    $seqStr = str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
                    
                    // Ambil sisipan lama atau reset ke 00? Idealnya reset ke 00 jika pindah rute
                    $finalKddk = $target . $seqStr . '00';
                    $unitup = DB::table('master_data_pelanggan')
                                ->where('idpel', $idpel)
                                ->value('unitup');

                    // Simpan ke MasterKddk juga sebagai wadah baru jika belum ada
                    \App\Models\MasterKddk::firstOrCreate(
                        ['kode_kddk' => $finalKddk],
                        [
                            'unitup' => $unitup ?? 'UNKNOWN', 
                            'is_active' => true,
                            'keterangan' => 'Generated via Move'
                        ]
                    );
                }

                // Update Mapping
                DB::table('mapping_kddk')
                    ->where('idpel', $idpel)
                    ->update([
                        'kddk' => $finalKddk,
                        'updated_at' => now()
                    ]);

                // 3. RAPIAKAN RUTE ASAL (Jika pindah rute, bukan reorder dalam rute sama)
                if ($sourcePrefix && $sourcePrefix !== substr($finalKddk, 0, 7)) {
                    $this->resequenceRoute($sourcePrefix);
                }

                $this->recordActivity(
                    'MOVE_SINGLE', 
                    "Memindahkan pelanggan {$idpel} ke Rute {$finalKddk}", 
                    $idpel
                );
            });

            $cacheKey = 'matrix_index_' . Auth::id() . '_' . date('Y-m');
            Cache::forget($cacheKey);

            return response()->json(['success' => true, 'message' => "Berhasil dipindahkan."]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PROSES SIMPAN: Grouping KDDK (Auto Sequence per Pelanggan)
     * FIXED: Urutan sesuai input, Smart Skip, dan Perbaikan Variabel.
     */
    public function storeKddkGroup(Request $request)
    {
        $request->validate([
            'prefix_code' => ['required', 'string', 'size:7', 'regex:/^[A-Z]{7}$/'],
            'sisipan' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'selected_idpels' => 'required|array|min:1',
            'unitup' => 'required|string',
            'kddk_code' => 'required|string'
        ]);

        $inputOrderedIdpels = $request->selected_idpels; 
        $prefix = strtoupper($request->prefix_code);
        $sisipan = $request->sisipan;
        $unitup = $request->unitup;
        $countData = count($inputOrderedIdpels);
        $startSeqStr = substr($request->kddk_code, 7, 3); 
        $startSeq = (int) $startSeqStr;

        // Decode Koordinat (Pastikan array)
        $coordUpdates = [];
        if ($request->has('coord_updates')) {
            $coordUpdates = json_decode($request->coord_updates, true);
            if (!is_array($coordUpdates)) $coordUpdates = [];
        }

        DB::transaction(function() use ($inputOrderedIdpels, $prefix, $sisipan, $startSeq, $countData, $unitup, $coordUpdates) {    
            
            // 1. BULK SHIFTING (GESER DATA LAMA)
            $needsShifting = DB::table('mapping_kddk')
                ->where('kddk', 'like', $prefix . '%')
                ->whereRaw('CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED) >= ?', [$startSeq])
                ->lockForUpdate()
                ->exists();

            if ($needsShifting) {
                DB::statement("
                    UPDATE mapping_kddk 
                    SET kddk = CONCAT(
                        LEFT(kddk, 7), 
                        LPAD(CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED) + ?, 3, '0'), 
                        RIGHT(kddk, 2)
                    )
                    WHERE kddk LIKE ? 
                    AND CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED) >= ?
                    ORDER BY kddk DESC
                ", [$countData, $prefix . '%', $startSeq]);
            }

            // 2. PREPARE DATA BARU (AMBIL OBJECT ID LAMA)
            $existingMap = DB::table('mapping_kddk')
                ->whereIn('idpel', $inputOrderedIdpels)
                ->pluck('objectid', 'idpel')
                ->toArray();

            $batchWithCoords = [];    
            $batchWithoutCoords = []; 
            $batchMaster = [];
            
            $now = now(); // Waktu sekarang untuk created_at & updated_at
            $currentSeq = $startSeq;

            foreach ($inputOrderedIdpels as $idpel) {
                $seqStr = str_pad($currentSeq, 3, '0', STR_PAD_LEFT);
                $fullKddk = $prefix . $seqStr . $sisipan;
                
                // Gunakan objectid lama atau buat baru
                $objId = $existingMap[$idpel] ?? ('groupkddk-' . Str::random(12));

                // DEFINISI ROW YANG SANGAT KETAT (JANGAN DIUBAH URUTANNYA)
                // Kita definisikan created_at manual agar Eloquent tidak bingung
                $row = [
                    'idpel'      => (string) $idpel,
                    'objectid'   => $objId,
                    'kddk'       => $fullKddk,
                    'enabled'    => 1,
                    'updated_at' => $now,
                    // Tambahkan created_at jika perlu (upsert akan menggunakannya untuk insert baru)
                    // 'created_at' => $now 
                ];

                // CEK KOORDINAT
                // Pastikan key coordUpdates[$idpel] ada dan isinya valid
                if (isset($coordUpdates[$idpel]) && isset($coordUpdates[$idpel]['lat']) && isset($coordUpdates[$idpel]['lng'])) {
                    
                    // Tambahkan key Lat/Long ke array $row
                    $row['latitudey'] = (double) $coordUpdates[$idpel]['lat'];
                    $row['longitudex'] = (double) $coordUpdates[$idpel]['lng'];
                    
                    // Masukkan ke keranjang Batch A (Update Peta)
                    $batchWithCoords[] = $row;
                } else {
                    // Masukkan ke keranjang Batch B (Update KDDK Saja)
                    $batchWithoutCoords[] = $row;
                }

                // DATA MASTER KDDK
                $batchMaster[] = [
                    'kode_kddk'  => $fullKddk,
                    'unitup'     => $unitup,
                    'is_active'  => 1,
                    'keterangan' => isset($coordUpdates[$idpel]) ? 'Generated w/ Coords' : 'Generated Bulk'
                ];

                $currentSeq++;
            }

            // 3. EKSEKUSI BATCH (TERPISAH)
            
            // A. Batch dengan Koordinat (Update KDDK + Lat + Long)
            if (!empty($batchWithCoords)) {
                // Upsert Array harus memiliki key yang seragam di setiap barisnya
                \App\Models\MappingKddk::upsert(
                    $batchWithCoords, 
                    ['idpel'], 
                    ['kddk', 'enabled', 'updated_at', 'objectid', 'latitudey', 'longitudex']
                );
            }

            // B. Batch TANPA Koordinat (Update KDDK saja)
            if (!empty($batchWithoutCoords)) {
                \App\Models\MappingKddk::upsert(
                    $batchWithoutCoords, 
                    ['idpel'], 
                    ['kddk', 'enabled', 'updated_at', 'objectid']
                );
            }

            // Simpan Master
            \App\Models\MasterKddk::insertOrIgnore($batchMaster);
        });

        Cache::forget('matrix_index_' . Auth::id() . '_' . date('Y-m'));

        return response()->json([
            'success' => true, 
            'message' => "Berhasil memproses $countData data. Koordinat diperbarui (jika ada)."
        ]);
    }

    /**
     * AJAX: Mendapatkan Nomor Urut (Digit 8-10) KDDK berikutnya
     */
    public function getNextSequence($prefix7)
    {
        // Validasi Prefix (7 Huruf)
        if (strlen($prefix7) !== 7 || !preg_match('/^[A-Z]{7}$/i', $prefix7)) {
            return response()->json(['sequence' => '001', 'message' => 'Invalid Prefix'], 400);
        }

        // Cari nomor urut tertinggi untuk prefix ini di tabel mapping
        $maxSequence = DB::table('mapping_kddk')
            ->where('kddk', 'like', $prefix7 . '%')
            // Ambil 3 digit (posisi 8, 9, 10) dan cast ke integer
            ->select(DB::raw('MAX(CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED)) as max_seq'))
            ->value('max_seq');

        // Hitung Next Sequence
        if ($maxSequence === null) {
            $nextSequence = 1;
        } else {
            $nextSequence = (int)$maxSequence + 1;
            if ($nextSequence > 999) {
                return response()->json(['sequence' => '999', 'message' => 'Sequence penuh', 'is_max' => true], 400);
            }
        }
        
        // Format jadi 3 digit (001, 010, 100)
        $formattedSequence = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

        return response()->json(['sequence' => $formattedSequence, 'message' => 'OK']);
    }

    /**
     * Simpan Assignment RBM
     */
    public function updateRbmAssignment(Request $request)
    {
        // Validasi dan Simpan data assignment RBM di sini
        // Contoh: Simpan ke tabel 'rbm_assignments' atau update master data
        
        return response()->json(['success' => true, 'message' => 'Penugasan RBM berhasil disimpan!']);
    }

    /**
     * Menghapus (Unmap) satu pelanggan dari rute (Drag ke Sampah)
     */
    public function removeIdpelKddk(Request $request)
    {
        $request->validate([
            'idpel' => 'required|exists:mapping_kddk,idpel',
        ]);

        DB::beginTransaction();
        try {
            $idpel = $request->idpel;

            // 1. Ambil Data Lama (Untuk mengetahui rute mana yang harus dirapikan)
            $oldData = DB::table('mapping_kddk')->where('idpel', $idpel)->first();
            $oldKddk = $oldData->kddk ?? null;

            // Ambil Prefix (7 Karakter Awal) untuk resequence
            $sourcePrefix = ($oldKddk && strlen($oldKddk) >= 7) ? substr($oldKddk, 0, 7) : null;

            // 2. Eksekusi Hapus (Set NULL)
            DB::table('mapping_kddk')
                ->where('idpel', $idpel)
                ->update([
                    'kddk' => null, 
                    'updated_at' => now()]
                );

            // 3. Catat Log Aktivitas
            $this->recordActivity(
                'REMOVE_SINGLE', 
                "Mengeluarkan pelanggan {$idpel} dari grup (Asal: {$oldKddk})", 
                $idpel
            );

            // 4. Rapikan Urutan Rute Lama (Resequence)
            if ($sourcePrefix) {
               $this->resequenceRoute($sourcePrefix);
            } 

            DB::commit();

            // 5. Hapus Cache
            if (Auth::check()) {
                $cacheKey = 'matrix_index_' . Auth::id() . '_' . date('Y-m');
                Cache::forget($cacheKey);
            }

            return response()->json([
                'success' => true,
                'message' => "Idpel {$idpel} berhasil dikeluarkan."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengurutkan Ulang (Reorder) Pelanggan dalam satu Rute
     */
    public function reorderIdpelKddk(Request $request)
    {
        $request->validate([
            'idpel' => 'required|exists:mapping_kddk,idpel', // Yang dipindahkan
            'target_idpel' => 'required|exists:mapping_kddk,idpel', // Dipindah ke posisi siapa
            'route_prefix' => 'required|string|size:7' // Prefix Rute (misal: A1BRBAA)
        ]);

        $movedIdpel = $request->idpel;
        $targetIdpel = $request->target_idpel;
        $prefix = $request->route_prefix;

        DB::transaction(function() use ($prefix, $movedIdpel, $targetIdpel) {
            // 1. Ambil semua pelanggan di rute ini, urutkan berdasarkan posisi saat ini (kddk)
            $items = DB::table('mapping_kddk')
                ->where('kddk', 'like', $prefix . '%')
                ->orderBy('kddk')
                ->get(['id', 'idpel', 'kddk']); // Ambil ID, IDPEL, KDDK lama

            // Konversi ke Collection untuk manipulasi array
            $collection = $items->map(function($item) {
                return $item->idpel;
            });

            // 2. Manipulasi Posisi Array
            // Cari index item yang dipindah dan target
            $movedIndex = $collection->search($movedIdpel);
            $targetIndex = $collection->search($targetIdpel);

            if ($movedIndex !== false && $targetIndex !== false) {
                // Hapus item dari posisi lama
                $collection->splice($movedIndex, 1);
                // Masukkan ke posisi baru (di tempat target berada)
                $collection->splice($targetIndex, 0, $movedIdpel);
            }

            // 3. Simpan Ulang dengan Nomor Urut Baru
            $seq = 1;
            foreach ($collection as $idpel) {
                // Cari data asli di array $items untuk mendapatkan info sisipan lama (opsional)
                // Di sini kita asumsikan sisipan tetap '00' atau direset jadi '00' agar rapi
                $originalItem = $items->firstWhere('idpel', $idpel);
                
                // Ambil sisipan lama (2 digit terakhir) atau default '00'
                $sisipan = substr($originalItem->kddk, -2); 

                // Bentuk KDDK Baru: [Prefix 7] + [Urut Baru 3] + [Sisipan 2]
                $newSeqStr = str_pad($seq, 3, '0', STR_PAD_LEFT);
                $newKddk = $prefix . $newSeqStr . $sisipan;

                // Update DB hanya jika berubah (Optimasi)
                if ($originalItem->kddk !== $newKddk) {
                    DB::table('mapping_kddk')
                        ->where('id', $originalItem->id) // Pakai ID primary key biar cepat
                        ->update([
                            'kddk' => $newKddk,
                            'updated_at' => now()
                        ]);
                }
                $seq++;
            }
        });

        return response()->json(['success' => true, 'message' => 'Urutan berhasil diperbarui.']);
    }

    /**
     * BULK MOVE: Pindahkan Banyak Pelanggan Sekaligus
     */
    public function bulkMove(Request $request)
    {
        $request->validate([
            'idpels' => 'required|array|min:1',
            'idpels.*' => 'exists:mapping_kddk,idpel',
            'target_kddk' => 'required|string',
        ]);

        $idpels = $request->idpels;
        $target = $request->target_kddk;

        // Pastikan panjang target sesuai format (Biasanya 7 digit: Prefix + Area + Rute)
        // Jika frontend mengirim kode yang salah (misal cuma 5 digit), proses ini akan skip.
        if (strlen($target) !== 7) {
            return response()->json([
                'success' => false, 
                'message' => 'Format Rute Tujuan Salah (Panjang kode tidak sesuai: ' . strlen($target) . ' digit). Refresh halaman.'
            ], 400);
        }

        try {
            DB::transaction(function() use ($idpels, $target) {

                // 1. Kumpulkan semua Prefix Asal yang terlibat (Bisa jadi idpel dari rute yg beda-beda)
                $sourcePrefixes = DB::table('mapping_kddk')
                    ->whereIn('idpel', $idpels)
                    ->select(DB::raw('LEFT(kddk, 7) as prefix'))
                    ->distinct()
                    ->pluck('prefix')
                    ->toArray();
                
                // Cek apakah target adalah Rute (7 Digit) atau KDDK Spesifik (12 Digit)
                // Biasanya Bulk Move diarahkan ke Rute (7 Digit) agar sequence dibuatkan otomatis
                if (strlen($target) === 7) {
                    
                    // 1. Cari Max Sequence saat ini di rute target
                    $maxSeq = DB::table('mapping_kddk')
                        ->where('kddk', 'like', $target . '%')
                        ->max(DB::raw('CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED)'));
                    $currentSeq = $maxSeq ? ($maxSeq + 1) : 1;

                    // 2. Loop setiap pelanggan
                    foreach ($idpels as $idpel) {
                        $seqStr = str_pad($currentSeq, 3, '0', STR_PAD_LEFT);
                        $finalKddk = $target . $seqStr . '00'; // Default sisipan 00

                        // Update Mapping
                        DB::table('mapping_kddk')
                            ->where('idpel', $idpel)
                            ->update([
                                'kddk' => $finalKddk,
                                'updated_at' => now()
                            ]);
                        
                        // Increment sequence untuk pelanggan berikutnya
                        $currentSeq++;
                    }
                }

                // 3. RAPIAKAN SEMUA RUTE ASAL
                foreach ($sourcePrefixes as $prefix) {
                    if ($prefix && $prefix !== $target) { // Jangan rapikan target dulu (sudah rapi dari loop di atas)
                        $this->resequenceRoute($prefix);
                    }
                }
                
                $count = count($idpels);
                $this->recordActivity(
                    'BULK_MOVE', 
                    "Memindahkan massal {$count} pelanggan ke Rute {$target}", 
                    "{$count} Pcs"
                );
            });

            $cacheKey = 'matrix_index_' . Auth::id() . '_' . date('Y-m');
            Cache::forget($cacheKey);

            return response()->json(['success' => true, 'message' => count($idpels) . " Pelanggan berhasil dipindahkan."]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * BULK REMOVE: Keluarkan Banyak Pelanggan Sekaligus
     */
    public function bulkRemove(Request $request)
    {
        $request->validate([
            'idpels' => 'required|array|min:1',
            'idpels.*' => 'exists:mapping_kddk,idpel',
        ]);

        try {

            // 1. Kumpulkan Prefix Asal
            $sourcePrefixes = DB::table('mapping_kddk')
                ->whereIn('idpel', $request->idpels)
                ->select(DB::raw('LEFT(kddk, 7) as prefix'))
                ->distinct()
                ->pluck('prefix')
                ->toArray();

            // 2. Proses Hapus
            DB::table('mapping_kddk')
                ->whereIn('idpel', $request->idpels)
                ->update([
                    'kddk' => null,
                    'updated_at' => now()
                ]);
            
            $count = count($request->idpels);
            $this->recordActivity(
                'BULK_REMOVE', 
                "Mengeluarkan massal {$count} pelanggan dari grup", 
                "{$count} Pcs"
            );

            // 3. RAPIAKAN RUTE ASAL
            foreach ($sourcePrefixes as $prefix) {
                if ($prefix) {
                    $this->resequenceRoute($prefix);
                }
            }

            $cacheKey = 'matrix_index_' . Auth::id() . '_' . date('Y-m');
            Cache::forget($cacheKey);

            return response()->json(['success' => true, 'message' => count($request->idpels) . " Pelanggan berhasil dikeluarkan."]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mengambil Data Riwayat Aktivitas (AJAX)
     */
    public function getAuditLogs(Request $request, $unit)
    {
        // Ambil 50 log terakhir (Global atau bisa difilter per Unit jika struktur DB mendukung)
        // Untuk saat ini kita ambil global latest karena tabel audit_log belum ada kolom 'unit_code'
        // Jika ingin spesifik unit, kita perlu join, tapi untuk monitoring umum latest sudah cukup.
        
        $logs = \App\Models\AuditLog::with('user')
                    ->latest()
                    ->limit(50)
                    ->get();

        return view('team.matrix_kddk.partials.audit_log_list', compact('logs'));
    }
    
    /**
     * Helper: Merapikan urutan nomor (sequence) dalam satu rute
     * Contoh: 001, 003, 005 -> menjadi -> 001, 002, 003
     */
    private function resequenceRoute($routePrefix)
    {
        // 1. Ambil semua pelanggan di rute ini
        // Urutkan berdasarkan kddk lama (ASC) agar urutan relatifnya tetap terjaga
        $customers = DB::table('mapping_kddk')
            ->where('kddk', 'like', $routePrefix . '%')
            ->whereNotNull('kddk')
            ->orderBy('kddk', 'asc')
            ->lockForUpdate()
            ->get();

        $totalFound = count($customers);
        $updatedCount = 0;
        $seq = 1;

        foreach ($customers as $c) {
            // Buat nomor urut baru (001, 002, dst)
            $newSeqStr = str_pad($seq, 3, '0', STR_PAD_LEFT);
            $currentSuffix = (strlen($c->kddk) >= 12) ? substr($c->kddk, -2) : '00';
            
            if (!ctype_digit($currentSuffix)) {
                $currentSuffix = '00';
            }

            // KDDK Baru
            $newKddk = $routePrefix . $newSeqStr . $currentSuffix;

            // Update hanya jika kode berubah
            if ($c->kddk !== $newKddk) {
                DB::table('mapping_kddk')->where('id', $c->id)->update([
                    'kddk' => $newKddk,
                    'updated_at' => now()
                ]);
                $updatedCount++;
            }
            $seq++;
        }

        return [
            'prefix_used' => $routePrefix,
            'total_items_found' => $totalFound,
            'total_updated' => $updatedCount
        ];
    }

    /**
     * Helper: Mencatat Aktivitas ke Audit Log
     */
    private function recordActivity($action, $description, $targetRef = null)
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action_type' => $action,
                'description' => $description,
                'target_reference' => $targetRef,
                'ip_address' => request()->ip()
            ]);
        } catch (\Exception $e) {
            // Jangan biarkan error logging menghentikan proses utama
            \Illuminate\Support\Facades\Log::error("Gagal mencatat audit: " . $e->getMessage());
        }
    }

    /**
     * Cetak Lembar Kerja Lapangan (Print View)
     */
    public function printWorksheet(Request $request, $unit)
    {
        $unitCode = urldecode($unit);
        $area = $request->area;
        $route = $request->route; // Opsional (bisa cetak per Area atau per Rute spesifik)

        if (!$area) {
            return back()->with('error', 'Pilih Area terlebih dahulu untuk mencetak.');
        }

        // 1. Ambil Data Pelanggan (Urut Sesuai Sequence)
        $query = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->select(
                'mapping_kddk.kddk', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex',
                'master_data_pelanggan.idpel', 'master_data_pelanggan.nomor_meter_kwh',
                'master_data_pelanggan.tarif', 'master_data_pelanggan.daya'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->where('mapping_kddk.enabled', 1);

        if ($area) $query->where('mapping_kddk.kddk', 'like', '___' . $area . '%');
        if ($route) $query->where('mapping_kddk.kddk', 'like', '_____' . $route . '%');

        // Urutkan berdasarkan Kode Rute Lengkap (Sequence otomatis terurut)
        $data = $query->orderBy('mapping_kddk.kddk', 'asc')->get();

        if ($data->isEmpty()) {
            return back()->with('error', 'Tidak ada data pelanggan untuk dicetak.');
        }

        // 2. Generate Link Navigasi (Titik Awal)
        // Kita ambil koordinat pelanggan pertama yang valid sebagai titik start navigasi
        $firstValid = $data->whereNotNull('latitudey')->first();
        $qrUrl = "";
        if ($firstValid) {
            // Link Google Maps Directions
            $qrUrl = "https://www.google.com/maps/dir/?api=1&destination={$firstValid->latitudey},{$firstValid->longitudex}";
        }

        // 3. Info Header
        $info = [
            'unit' => $unitCode,
            'area' => $area,
            'route' => $route ?? 'SEMUA',
            'total' => $data->count(),
            'date' => now()->format('d-m-Y H:i')
        ];

        return view('team.matrix_kddk.print_worksheet', compact('data', 'qrUrl', 'info'));
    }
    
    /**
     * API Pencarian Global (Untuk Lazy Load)
     * Mengembalikan lokasi rute pelanggan agar bisa dibuka otomatis
     */
    public function searchCustomer(Request $request, $unit)
    {
        $term = $request->keyword;
        $unitCode = urldecode($unit);

        if (strlen($term) < 3) return response()->json([]); // Minimal 3 karakter

        $results = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->select(
                'mapping_kddk.idpel',
                'mapping_kddk.kddk',
                'master_data_pelanggan.nomor_meter_kwh'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->where(function($q) use ($term) {
                $q->where('mapping_kddk.idpel', 'like', "%{$term}%")
                  ->orWhere('master_data_pelanggan.nomor_meter_kwh', 'like', "%{$term}%");
            })
            ->limit(20) // Batasi hasil agar ringan
            ->get()
            ->map(function($item) {
                // Parse Lokasi Rute dari KDDK (A1BRBAA...)
                // Area: Digit 4-5 (RB)
                // Digit 6: Kelompok (A)
                // Digit 7: Sub (A)
                // ID Target DOM: route-RB-AA
                $area = substr($item->kddk, 3, 2);
                $digit6 = substr($item->kddk, 5, 1);
                $digit7 = substr($item->kddk, 6, 1);
                
                return [
                    'idpel' => $item->idpel,
                    'text' => "{$item->idpel} - {$item->nomor_meter_kwh}",
                    'target_route_id' => "route-{$area}-{$digit6}{$digit7}", // ID accordion rute
                    'target_area_id' => "area-{$area}", // ID accordion area
                    'target_d6_id' => "d6-{$area}-{$digit6}", // ID accordion digit 6
                    'area_code' => $area,
                    'route_code' => $digit6 . $digit7
                ];
            });

        return response()->json($results);
    }

    /**
     * SIMPAN URUTAN RUTE BARU (HASIL VISUAL REORDER)
     */
    public function saveRouteSequence(Request $request)
    {
        $request->validate([
            'route_prefix' => 'required|string|size:7', // Misal: A1BRBAA
            'ordered_idpels' => 'required|array|min:1'  // Array IDPEL urut [id1, id2, id3...]
        ]);

        $prefix = $request->route_prefix;
        $idpels = $request->ordered_idpels;

        DB::beginTransaction();
        try {
            // 1. Ambil data existing untuk menjaga sisipan (opsional) atau reset sisipan
            // Disini kita reset sisipan ke '00' agar urutan bersih.
            
            $seq = 1;
            foreach ($idpels as $idpel) {
                // Format KDDK Baru: Prefix(7) + Urut(3) + Sisipan(00)
                $seqStr = str_pad($seq, 3, '0', STR_PAD_LEFT);
                $newKddk = $prefix . $seqStr . '00';

                DB::table('mapping_kddk')
                    ->where('idpel', $idpel)
                    ->update([
                        'kddk' => $newKddk,
                        'updated_at' => now()
                    ]);
                
                $seq++;
            }

            // Catat Log
            $count = count($idpels);
            $this->recordActivity('VISUAL_REORDER', "Mengurutkan ulang visual {$count} pelanggan pada rute {$prefix}", $prefix);

            DB::commit();
            
            // Hapus Cache
            Cache::forget('matrix_index_' . Auth::id() . '_' . date('Y-m'));

            return response()->json(['success' => true, 'message' => "Urutan rute berhasil diperbarui!"]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    // --- Helper Hirarki (Copy dari kode lama Anda) ---
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