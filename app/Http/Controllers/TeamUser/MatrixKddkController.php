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
        $query = DB::table('master_data_pelanggan')
            // 1. Join untuk Status Validasi (Tidak mempengaruhi filtering utama)
            ->leftJoin('temporary_mappings', 'master_data_pelanggan.idpel', '=', 'temporary_mappings.idpel')
            
            // 2. Join ke Mapping KDDK Khusus yang ENABLED = 1 (Data Aktif)
            ->leftJoin('mapping_kddk', function($join) {
                $join->on('master_data_pelanggan.idpel', '=', 'mapping_kddk.idpel')
                     ->where('mapping_kddk.enabled', 1);
            })

            ->select(
                'master_data_pelanggan.*',

                // Ambil Data dari Mapping yang Enabled=1
                'mapping_kddk.kddk as current_kddk',
                'mapping_kddk.user_pendataan',
                'mapping_kddk.latitudey',
                'mapping_kddk.longitudex',
                'mapping_kddk.foto_kwh',
                'mapping_kddk.foto_bangunan',
                'mapping_kddk.namagd',

                'temporary_mappings.is_validated',
                'temporary_mappings.ket_validasi',

            )
            ->where('master_data_pelanggan.unitup', $unitCode)

            ->where(function($q) {
                $q->whereNull('mapping_kddk.kddk')
                  ->orWhere('mapping_kddk.kddk', '=', '');
            });
            
        // Filter Pencarian (Opsional)
        if ($request->has('search') && $request->search != '') {
            $searchTerm = '%' . $request->search . '%';
            
            $query->where(function($q) use ($searchTerm) {
                $q->where('master_data_pelanggan.idpel', 'like', $searchTerm)
                  ->orWhere('master_data_pelanggan.nomor_meter_kwh', 'like', $searchTerm);
            });
        }

        $customers = $query->paginate(8)->withPath(route('team.matrix_kddk.details', ['unit' => $unitCode]));

        $viewData = compact('customers', 'unit', 'activePeriod', 'autoCodes', 'subUnits', 'kddkConfig');

        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.detail_content', $viewData);
        }

        return view('team.matrix_kddk.details', $viewData);
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
            ->select(
                'mapping_kddk.idpel', 
                'mapping_kddk.kddk', 
                'mapping_kddk.user_pendataan',
                'master_data_pelanggan.nomor_meter_kwh', 
                'master_data_pelanggan.nomor_meter_kwh',
                'master_data_pelanggan.nomor_meter_kwh'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->whereNotNull('mapping_kddk.kddk')
            ->where('mapping_kddk.kddk', '!=', '')
            ->whereRaw('LENGTH(mapping_kddk.kddk) >= 5')
            ->orderBy('mapping_kddk.kddk') 
            ->get();

        // 3. GROUPING 3 LEVEL: AREA -> DIGIT 6 -> DIGIT 7
        $groupedData = $rawCustomers->groupBy(function($item) {
            // Level 1: Area (Digit 4-5)
            return substr($item->kddk, 3, 2); 
        })->map(function($areaGroup) {
            // Level 2: Digit 6 (Kelompok Rute A, B, C...)
            return $areaGroup->groupBy(function($item) {
                return substr($item->kddk, 5, 1); 
            })->map(function($digit6Group) {
                // Level 3: Digit 7 (Sub Rute A, B, C...)
                return $digit6Group->groupBy(function($item) {
                    return substr($item->kddk, 6, 1); 
                });
            });
        });

        // Config & Officers
        $kddkConfig = \App\Models\AppSetting::findValue('kddk_config_data', auth()->user()->hierarchy_level_code, []);
        $areaLabels = collect($kddkConfig['areas'] ?? [])->pluck('label', 'code');
        $officers = \App\Models\User::whereHas('role', fn($q) => $q->where('name', 'appuser'))->get();

        $viewData = compact('unitCode', 'hierarchy', 'groupedData', 'officers', 'areaLabels','kddkConfig');

        if ($request->has('is_ajax')) {
            return view('team.matrix_kddk.partials.rbm_manage_content', $viewData);
        }
        return view('team.matrix_kddk.rbm_manage', $viewData);
    }

    // --- 4. METHOD BARU: AJAX MAP DATA ---
    public function getMapData(Request $request, $unit)
    {
        $unitCode = urldecode($unit);
        $area = $request->area; // Filter Area
        $route = $request->route; // Filter Rute

        if (!$area) return response()->json([]);

        $query = DB::table('mapping_kddk')
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            ->select(
                'mapping_kddk.kddk', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex', 
                'mapping_kddk.idpel', 'master_data_pelanggan.nomor_meter_kwh'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->where('mapping_kddk.enabled', 1)
            ->whereNotNull('mapping_kddk.latitudey')
            ->whereNotNull('mapping_kddk.longitudex');

        if ($area) $query->where('mapping_kddk.kddk', 'like', '___' . $area . '%');
        if ($route) $query->where('mapping_kddk.kddk', 'like', '_____' . $route . '%');
        
        $data = $query->limit(2000)->get()->map(function($item) {
            $mapsUrl = "https://www.google.com/maps?q={$item->latitudey},{$item->longitudex}";
            return [
                'lat' => $item->latitudey,
                'lng' => $item->longitudex,
                'seq' => substr($item->kddk, 7, 3),
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
        $filename = "RBM_DATA_{$unitCode}_" . date('Ymd_His') . ".csv";

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['KDDK', 'No Urut', 'ID Pelanggan', 'Nama', 'Alamat', 'No Meter', 'Latitude', 'Longitude', 'Petugas'];

        $callback = function() use ($unitCode, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            DB::table('mapping_kddk')
                ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->select(
                    'mapping_kddk.kddk',
                    'mapping_kddk.idpel',
                    'master_data_pelanggan.nomor_meter_kwh',
                    'master_data_pelanggan.alamat',
                    'master_data_pelanggan.nomor_meter_kwh',
                    'mapping_kddk.latitudey',
                    'mapping_kddk.longitudex',
                    'mapping_kddk.user_pendataan'
                )
                ->where('master_data_pelanggan.unitup', $unitCode)
                ->orderBy('mapping_kddk.kddk')
                ->chunk(1000, function($rows) use ($file) {
                    foreach ($rows as $row) {
                        $seq = substr($row->kddk, 7, 3);
                        fputcsv($file, [
                            $row->kddk,
                            $seq,
                            $row->idpel,
                            $row->nomor_meter_kwh,
                            $row->nomor_meter_kwh,
                            $row->nomor_meter_kwh,
                            $row->latitudey,
                            $row->longitudex,
                            $row->user_pendataan
                        ]);
                    }
                });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
     */
    public function storeKddkGroup(Request $request)
    {
        // 1. Validasi Input (Hanya perlu Prefix 7 Digit dan Sisipan)
        $request->validate([
            'prefix_code' => ['required', 'string', 'size:7', 'regex:/^[A-Z]{7}$/'], // 7 Huruf
            'sisipan' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'], // 2 Angka
            'selected_idpels' => 'required|array|min:1',
            'unitup' => 'required|string'
        ]);

        $prefix = strtoupper($request->prefix_code); // Contoh: A1BRBAA
        $sisipan = $request->sisipan; // Contoh: 00
        $selectedIdpels = $request->selected_idpels;
        $unitup = $request->unitup;

        // 2. Cari Nomor Urut Terakhir di Database untuk Rute ini
        // Kita cari MAX dari digit ke-8,9,10
        $lastSequence = DB::table('mapping_kddk')
            ->where('kddk', 'like', $prefix . '%')
            ->max(DB::raw('CAST(SUBSTRING(kddk, 8, 3) AS UNSIGNED)'));

        $startSequence = $lastSequence ? ($lastSequence + 1) : 1;

        // 3. Pastikan IDPEL Valid
        $validIdpels = DB::table('master_data_pelanggan')
            ->whereIn('idpel', $selectedIdpels)
            ->pluck('idpel') // Ambil IDPEL saja
            // Opsional: Urutkan agar penomoran rapi (misal berdasarkan alamat atau gardu)
            // ->orderBy('alamat') 
            ->toArray();

        if (empty($validIdpels)) {
            return response()->json(['message' => 'Tidak ada pelanggan valid.'], 422);
        }

        DB::transaction(function() use ($validIdpels, $prefix, $sisipan, $startSequence, $unitup) {
            
            // A. Simpan Master Rute (Wadah) - Cukup simpan Prefix-nya sebagai referensi Rute
            // Atau simpan KDDK pertama sebagai perwakilan? 
            // Untuk konsep baru ini, MasterKddk sebaiknya menyimpan "RUTE" (7 digit) atau tetap per kode unik.
            // Agar aman, kita simpan per kode unik yang terbentuk.
            
            $currentSeq = $startSequence;

            foreach ($validIdpels as $idpel) {
                
                // Generate Kode Unik per Pelanggan
                // Format: [Prefix 7] + [Urut 3] + [Sisip 2]
                $seqString = str_pad($currentSeq, 3, '0', STR_PAD_LEFT);
                $fullKddk = $prefix . $seqString . $sisipan;

                // 1. Simpan ke Master (Agar tercatat sebagai kode valid)
                \App\Models\MasterKddk::insertOrIgnore(
                    ['kode_kddk' => $fullKddk],
                    [
                        'unitup' => $unitup,
                        'keterangan' => 'Generated Sequence',
                        'is_active' => true
                    ]
                );

                // 2. Update/Insert ke Mapping (Transaksi)
                // Cek eksistensi
                $existing = DB::table('mapping_kddk')->where('idpel', $idpel)->first();

                if ($existing) {
                    DB::table('mapping_kddk')->where('id', $existing->id)->update([
                        'kddk' => $fullKddk,
                        'updated_at' => now()
                    ]);
                } else {
                    DB::table('mapping_kddk')->insert([
                        'objectid' => (string) Str::uuid(),
                        'idpel' => $idpel,
                        'kddk' => $fullKddk,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $currentSeq++; // Lanjut ke nomor berikutnya

                $count = count($validIdpels);
                    $this->recordActivity(
                    'GENERATE_GROUP', 
                    "Membentuk grup baru untuk {$count} pelanggan di Rute {$prefix}", 
                    $prefix
                );
            }
        });

        $cacheKey = 'matrix_index_' . Auth::id() . '_' . date('Y-m');
        Cache::forget($cacheKey);

        $count = count($validIdpels);
        $endSeq = $startSequence + $count - 1;
        
        return response()->json([
            'success' => true, 
            'message' => "Berhasil generate $count kode. Urutan: " . str_pad($startSequence, 3,'0',0) . " s.d " . str_pad($endSeq, 3,'0',0)
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
     * Menghapus IDPEL dari Grup KDDK (Set KDDK to NULL)
     */
    public function removeIdpelKddk(Request $request)
    {
        $request->validate([
            'idpel' => 'required|exists:mapping_kddk,idpel',
        ]);

        try {

            // 1. Ambil Kode Lama
            $oldKddk = DB::table('mapping_kddk')->where('idpel', $request->idpel)->value('kddk');
            $sourcePrefix = substr($oldKddk, 0, 7);

            // Update kolom kddk menjadi NULL
            DB::table('mapping_kddk')
                ->where('idpel', $request->idpel)
                ->update([
                    'kddk' => null, // Kosongkan KDDK
                    'updated_at' => now()
                ]);

            $this->recordActivity(
                'MOVE_SINGLE', 
                "Memindahkan pelanggan {$idpel} ke Rute {$finalKddk}", 
                $idpel
            );

            // 3. RAPIAKAN RUTE ASAL
            if ($sourcePrefix) {
                $this->resequenceRoute($sourcePrefix);
            }

            

            $cacheKey = 'matrix_index_' . Auth::id() . '_' . date('Y-m');
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => "IDPEL {$request->idpel} berhasil dikeluarkan dari grup."
            ]);
        } catch (\Exception $e) {
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
     * Helper: Merapikan urutan nomor (sequence) dalam satu rute
     * Contoh: 001, 003, 005 -> menjadi -> 001, 002, 003
     */
    private function resequenceRoute($routePrefix)
    {
        // Ambil semua pelanggan di rute tersebut, urutkan berdasarkan kddk yang sekarang (berantakan)
        $customers = DB::table('mapping_kddk')
            ->where('kddk', 'like', $routePrefix . '%')
            ->orderBy('kddk')
            ->get();

        $seq = 1;
        foreach ($customers as $c) {
            // Bentuk KDDK Baru: Prefix (7) + Urut Baru (3) + Sisipan Lama (2)
            $oldSisip = substr($c->kddk, 10, 2); 
            $newSeqStr = str_pad($seq, 3, '0', STR_PAD_LEFT);
            $newKddk = $routePrefix . $newSeqStr . $oldSisip;

            // Update hanya jika berubah (untuk optimasi)
            if ($c->kddk !== $newKddk) {
                DB::table('mapping_kddk')
                    ->where('id', $c->id)
                    ->update([
                        'kddk' => $newKddk,
                        'updated_at' => now()
                    ]);
            }
            $seq++;
        }
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