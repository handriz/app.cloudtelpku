<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\HierarchyLevel;
use App\Models\MasterKddk;
use App\Models\AppSetting; 
use App\Models\User;
use Illuminate\Support\Str;

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

        $hierarchyFilter = $this->getHierarchyFilterForJoin($user);

        // 2. QUERY UTAMA
        $query = DB::table('master_data_pelanggan')
            // PERBAIKAN 1: JOIN KE 'CODE', BUKAN 'NAME'
            ->join('hierarchy_levels as h_ulp', 'master_data_pelanggan.unitup', '=', 'h_ulp.code')
            ->leftJoin('hierarchy_levels as h_up3', 'h_ulp.parent_code', '=', 'h_up3.code')

            ->leftJoin('temporary_mappings', function($join) use ($activePeriod) {
                $join->on('master_data_pelanggan.idpel', '=', 'temporary_mappings.idpel')
                     ->whereRaw("DATE_FORMAT(temporary_mappings.created_at, '%Y-%m') = ?", [$activePeriod]);
            })
            
            ->select(
                // PERBAIKAN 2: Ambil Nama dari Hirarki untuk ditampilkan sebagai 'unit_layanan'
                'h_ulp.name as unit_layanan', 
                // Kita ambil juga kodenya untuk keperluan link/drill-down nanti
                'master_data_pelanggan.unitup as unit_code',
                
                'h_ulp.kddk_code as kode_ulp',
                'h_up3.name as unit_induk_name',
                'h_up3.kddk_code as kode_up3',

                DB::raw('COUNT(master_data_pelanggan.id) as target_pelanggan'),
                DB::raw('COUNT(temporary_mappings.id) as realisasi'),
                DB::raw('COUNT(CASE WHEN temporary_mappings.is_validated = 1 THEN 1 END) as valid'),
                DB::raw('COUNT(CASE WHEN temporary_mappings.ket_validasi LIKE "rejected_%" THEN 1 END) as ditolak')
            );

        if (!$user->hasRole('admin') && $hierarchyFilter) {
            $query->where($hierarchyFilter['column'], $hierarchyFilter['code']);
        }

        // PERBAIKAN 3: Group By harus konsisten dengan Select
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

        $matrixData = $rawMatrix->groupBy(function($item) {
            return $item->unit_induk_name ?? 'LAINNYA';
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
            // Jika ULP
            if ($currentUnit->unit_type === 'ULP') {
                $autoCodes['ulp'] = $currentUnit->kddk_code;
                if ($currentUnit->parent) {
                    $autoCodes['up3'] = $currentUnit->parent->kddk_code;
                }
                // Cek Sub Unit
                $subUnits = $currentUnit->children()->where('unit_type', 'SUB_ULP')->orderBy('kddk_code')->get();
                if ($subUnits->isNotEmpty()) {
                    $autoCodes['sub'] = ''; // Kosongkan agar user wajib pilih
                }
            } 
            // Jika SUB ULP (Jaga-jaga)
            elseif ($currentUnit->unit_type === 'SUB_ULP') {
                $autoCodes['sub'] = $currentUnit->kddk_code;
                if ($currentUnit->parent) {
                    $autoCodes['ulp'] = $currentUnit->parent->kddk_code;
                    if ($currentUnit->parent->parent) $autoCodes['up3'] = $currentUnit->parent->parent->kddk_code;
                }
            }
        }

        // 3. QUERY DATA PELANGGAN
        $query = DB::table('master_data_pelanggan')
            ->leftJoin('temporary_mappings', 'master_data_pelanggan.idpel', '=', 'temporary_mappings.idpel')
            ->leftJoin('mapping_kddk', 'master_data_pelanggan.idpel', '=', 'mapping_kddk.idpel') // Join untuk lihat KDDK eksisting
            ->select(
                'master_data_pelanggan.*',
                'mapping_kddk.kddk as current_kddk',
                'temporary_mappings.is_validated',
                'temporary_mappings.ket_validasi'
            )
            ->where('master_data_pelanggan.unitup', $unitCode)
            ->whereNull('mapping_kddk.kddk');
            
        // Filter Pencarian (Opsional)
        if ($request->has('search') && $request->search != '') {
            $query->where(function($q) use ($request) {
                $q->where('master_data_pelanggan.idpel', 'like', '%' . $request->search . '%')
                  ->orWhere('master_data_pelanggan.nomor_meter_kwh', 'like', '%' . $request->search . '%');
            });
        }

        $customers = $query->paginate(8);
        $customers->withPath(route('team.matrix_kddk.details', ['unit' => $unitCode]));

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

        $viewData = compact('unitCode', 'hierarchy', 'groupedData', 'officers', 'areaLabels');

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
            return [
                'lat' => $item->latitudey,
                'lng' => $item->longitudex,
                'seq' => substr($item->kddk, 7, 3),
                'info' => "<b>Seq: " . substr($item->kddk, 7, 3) . "</b><br>{$item->idpel}<br>" . substr($item->nomor_meter_kwh, 0, 15)
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
                
                $finalKddk = $target;

                // LOGIKA BARU: Jika target cuma 7 digit (Prefix Rute), Generate Sequence Baru
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
            });

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
                \App\Models\MasterKddk::firstOrCreate(
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
                        'objectid' => (string) \Illuminate\Support\Str::uuid(),
                        'idpel' => $idpel,
                        'kddk' => $fullKddk,
                        'enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $currentSeq++; // Lanjut ke nomor berikutnya
            }
        });

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
            // Update kolom kddk menjadi NULL
            DB::table('mapping_kddk')
                ->where('idpel', $request->idpel)
                ->update([
                    'kddk' => null, // Kosongkan KDDK
                    'updated_at' => now()
                ]);

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