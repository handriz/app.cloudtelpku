<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use App\Models\MappingKddk;
use App\Models\TemporaryMapping;
use App\Models\MasterDataPelanggan;
use App\Models\HierarchyLevel;
use App\Models\MatrixSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessMappingKddkImport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;


class MappingKddkController extends Controller
{
    public function index(Request $request)
    {
        // 1. Inisialisasi
        $user = Auth::user();
        $hierarchyFilter = $this->getHierarchyFilterForJoin($user);
        $search = $request->input('search');
        $sortColumn = $request->input('sort', 'mapping_kddk.created_at');
        $sortDirection = $request->input('direction', 'desc');

        // 2. Tentukan Tipe Pencarian (IDPEL Search vs Other/No Search)
        $isIdpelSearch = false;
        if ($search && is_numeric($search) && strlen($search) == 12) {
            // Cek apakah IDPEL ini ada & boleh diakses user
            $existsQuery = MappingKddk::query()->where('mapping_kddk.idpel', $search)
                ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                    // Cek di tabel master untuk hirarki
                    return $query->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                        ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
                });
            if ($existsQuery->exists()) {
                $isIdpelSearch = true;
            }
        }

        // 3. Bangun Query Utama
        $query = MappingKddk::query()
            ->select('mapping_kddk.*', 'master_data_pelanggan.unitup', 'master_data_pelanggan.unitap', 'master_data_pelanggan.nama_gardu', 'master_data_pelanggan.daya', 'master_data_pelanggan.tarif',)
            // Kita gunakan LEFT JOIN agar data mapping tetap tampil meskipun data master pelanggan tidak ada
            ->leftJoin('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            // Terapkan filter hirarki HANYA untuk non-admin
            ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                // filter hirarki harus menggunakan kolom dari master_data_pelanggan
                return $query->where($hierarchyFilter['column'], $hierarchyFilter['code']);
            });

        // 4. Terapkan Logika Pencarian
        if ($isIdpelSearch) {

            // SKENARIO 1: User mencari IDPEL. Tampilkan semua objectid.
            $query->where('mapping_kddk.idpel', $search);
            $query->orderBy('mapping_kddk.enabled', 'DESC');
            $query->orderByRaw("
                CASE 
                    WHEN mapping_kddk.ket_validasi = 'verified' THEN 1
                    WHEN mapping_kddk.ket_validasi = 'superseded' THEN 2
                    WHEN mapping_kddk.ket_validasi = 'recalled' THEN 3
                    WHEN mapping_kddk.ket_validasi = 'rejected' THEN 4
                    ELSE 5
                END ASC
            ");
            $query->orderBy('mapping_kddk.created_at', 'desc');
        } else {

            // 1. Buat query dalam (ranking)
            $rankingQuery = DB::table('mapping_kddk')
                ->select(
                    'mapping_kddk.id', // Hanya butuh ID
                    DB::raw("ROW_NUMBER() OVER(
                        PARTITION BY mapping_kddk.idpel 
                        ORDER BY 
                            mapping_kddk.enabled DESC, -- Prioritas 1: enabled = true
                            CASE mapping_kddk.ket_validasi
                                WHEN 'verified' THEN 1
                                WHEN 'superseded' THEN 2
                                WHEN 'recalled' THEN 3
                                WHEN 'rejected' THEN 4
                                ELSE 5
                            END ASC, -- Prioritas 2: Status 'verified'
                            mapping_kddk.created_at DESC -- Prioritas 3: Paling baru
                    ) as rn")
                )
                // Filter hirarki HARUS diterapkan di dalam ranking
                ->when(!$user->hasRole('admin'), function ($q) use ($hierarchyFilter) {
                    $q->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                        ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
                });
            // Terapkan filter pencarian (non-IDPEL) di dalam ranking juga
            if ($search) {
                $rankingQuery->where(function ($q) use ($search) {
                    $q->where('mapping_kddk.idpel', 'like', "%{$search}%")
                        ->orWhere('mapping_kddk.nokwhmeter', 'like', "%{$search}%")
                        ->orWhere('mapping_kddk.user_pendataan', 'like', "%{$search}%");
                });
            }

            // 2. Buat SubQuery (query luar) yang mengambil hanya rank 1
            $subQuery = DB::table($rankingQuery, 'ranked_mappings')
                ->select('id')
                ->where('rn', 1);

            // 3. Query utama HANYA mengambil ID yang ada di hasil SubQuery
            $query->whereIn('mapping_kddk.id', $subQuery);
            // Terapkan sorting HANYA untuk general search (non-IDPEL)    
            $query->orderBy($sortColumn, $sortDirection);
        }

        // 5. Terapkan paginasi
        if ($search) {
            $mappings = $query->simplePaginate(10)->withQueryString();
        } else {
            $mappings = $query->paginate(10)->withQueryString();
        }
        $pageIdpels = $mappings->pluck('idpel')->unique();
        $activeIdpels = MappingKddk::whereIn('idpel', $pageIdpels)
            ->where('enabled', true)
            ->pluck('idpel')
            ->toArray();

        // 6. Siapkan Data Header (Foto, Peta, Status)
        $searchedMapping = null;
        $mappingStatus = null;
        $searchedIdpel = null;

        if ($search) {
            $searchedIdpel = $search;

            // 6a. Coba cari data yang 'enabled' DULU untuk IDPEL ini
            $focusedQuery = MappingKddk::query()
                ->where('mapping_kddk.idpel', $searchedIdpel)
                ->where('mapping_kddk.enabled', true) // Prioritas: Enabled = 1
                ->latest() // Ambil yang terbaru jika ada beberapa yang enabled (jarang)
                ->first();

            if (!$focusedQuery) {
                // 6b. Jika tidak ada yang enabled, ambil saja data yang paling verified/terbaru
                // Kita gunakan hasil pertama dari Paginasi sebagai fallback
                $focusedQuery = $mappings->first();
            }

            $searchedMapping = $focusedQuery;

            if ($searchedMapping) {
                $mappingStatus = ($searchedMapping->enabled) ? 'valid' : 'unverified';
            } elseif ($isIdpelSearch) {
                // Kasus jika IDPEL dicari tapi tidak ada hasil
                $mappingStatus = 'unverified';
            }
        }

        // 7. Hitung data untuk kartu ringkasan
        // -------------------------------------------------------------
        // [MODIFIKASI] OPTIMASI STATISTIK MENGGUNAKAN TABLE MATRIX_SUMMARIES
        // -------------------------------------------------------------

        $totalPelanggan = 0;
        $totalMappingEnabled = 0;

        try {
            if ($user->hasRole('admin')) {
                // CARA ADMIN: Langsung jumlahkan kolom dari seluruh tabel MatrixSummary
                // Ini akan menghasilkan integer (angka), bukan object/string
                $totalPelanggan = MatrixSummary::sum('target_pelanggan');
                $totalMappingEnabled = MatrixSummary::sum('sudah_kddk');
            } else {
                // CARA USER UNIT: Cari baris spesifik
                $unitCode = $user->hierarchy_level_code;

                // Cari berdasarkan unit_code
                $summary = MatrixSummary::where('unit_code', $unitCode)->first();

                // Fallback: Cari di kolom lain jika unit_code kosong
                if (!$summary) {
                    $summary = MatrixSummary::where('unitup', $unitCode)
                        ->orWhere('unitap', $unitCode)
                        ->orWhere('unitupi', $unitCode)
                        ->first();
                }

                if ($summary) {
                    $totalPelanggan = $summary->target_pelanggan;
                    $totalMappingEnabled = $summary->sudah_kddk;
                }
            }
        } catch (\Exception $e) {
            // Jika tabel MatrixSummary tidak ditemukan/error, biarkan 0 atau lanjut logic lain
            \Log::error("Error menghitung MatrixSummary: " . $e->getMessage());
        }

        $rawPercent = ($totalPelanggan > 0) ? ($totalMappingEnabled / $totalPelanggan) * 100 : 0;
        $mappingPercentage = number_format($rawPercent, 1);
        // -------------------------------------------------------------
        // END OPTIMASI
        // -------------------------------------------------------------

        // 8. Siapkan semua data yang dibutuhkan oleh view
        $viewData = compact(
            'mappings',
            'totalMappingEnabled',
            'totalPelanggan',
            'mappingPercentage',
            'search',
            'sortColumn',
            'sortDirection',
            'mappingStatus',
            'searchedIdpel',
            'searchedMapping',
            'activeIdpels'
        );

        // 9. Logika untuk membedakan request biasa dan AJAX
        if ($request->has('is_ajax')) {
            return view('team.mapping-kddk.partials.index_content', $viewData);
        }
        return view('team.mapping-kddk.index', $viewData);
    }

    public function getMapCoordinates(Request $request)
    {
        $user = Auth::user();
        $hierarchyFilter = $this->getHierarchyFilterForJoin($user);
        $search = $request->input('search');

        // Query dasar yang lebih bersih, tanpa select awal
        $baseQuery = MappingKddk::query()
            ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                return $query->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                    ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
            })
            ->whereNotNull('mapping_kddk.latitudey')
            ->whereNotNull('mapping_kddk.longitudex');

        // SKENARIO 1: Tidak Ada Pencarian (Ambil Random Sample)
        if (!$search) {
            $initialCustomers = (clone $baseQuery)
                ->select('mapping_kddk.idpel', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex')
                ->latest('mapping_kddk.updated_at')
                ->limit(100)
                ->get();
            return response()->json(['searched' => [], 'nearby' => [], 'all' => $initialCustomers]);
        }

        // SKENARIO 2: Ada Pencarian
        $searchedCustomers = (clone $baseQuery)
            ->select('mapping_kddk.idpel', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex', 'mapping_kddk.namagd')
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('mapping_kddk.idpel', 'like', "%{$search}%")
                        ->orWhere('mapping_kddk.nokwhmeter', 'like', "%{$search}%");
                });
            })->get();

        $nearbyCustomers = collect();

        if ($searchedCustomers->isNotEmpty()) {
            $centerPoint = $searchedCustomers->first();

            $lat = (float) $centerPoint->latitudey;
            $lon = (float) $centerPoint->longitudex;
            $radiusKm = 0.2; // 200 meter

            // Hitung Selisih Derajat (Rumus Kasar)
            // 1 Derajat Latitude ~= 111 km
            $latChange = $radiusKm / 111;
            // 1 Derajat Longitude ~= 111 km * cos(latitude)
            $lonChange = $radiusKm / abs(111 * cos(deg2rad($lat)));

            // Tentukan Batas Kotak (Bounding Box)
            $minLat = $lat - $latChange;
            $maxLat = $lat + $latChange;
            $minLon = $lon - $lonChange;
            $maxLon = $lon + $lonChange;

            $nearbyCustomers = (clone $baseQuery)
                ->select(
                    'mapping_kddk.idpel',
                    'mapping_kddk.latitudey',
                    'mapping_kddk.longitudex',
                    'mapping_kddk.namagd'
                )

                // [OPTIMASI] Filter Kotak Dulu (Ringan bagi Database)
                // Menggunakan nama kolom asli sesuai tabel
                ->whereBetween('mapping_kddk.latitudey', [$minLat, $maxLat])
                ->whereBetween('mapping_kddk.longitudex', [$minLon, $maxLon])

                // Hitung Jarak Persis (Haversine) hanya untuk data di dalam kotak
                ->selectRaw("( 6371 * acos( cos( radians(?) ) * cos( radians( mapping_kddk.latitudey ) ) * cos( radians( mapping_kddk.longitudex ) - radians(?) ) + sin( radians(?) ) * sin( radians( mapping_kddk.latitudey ) ) ) ) AS distance", [$lat, $lon, $lat])

                // Exclude data yang sedang dicari agar tidak duplikat
                ->whereNotIn('mapping_kddk.idpel', $searchedCustomers->pluck('idpel')->toArray())

                ->orderBy("distance", "asc")
                ->limit(20) // Batasi 20 tetangga terdekat
                ->get();
        }

        return response()->json([
            'searched' => $searchedCustomers,
            'nearby' => $nearbyCustomers,
            'all' => []
        ]);
    }

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

    public function downloadFormat()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="format_upload_mapping_kddk.csv"',
        ];

        // Daftar header kolom sesuai dengan yang diharapkan oleh Importer Anda
        $columns = [
            'objectid',
            'idpel',
            'user_pendataan',
            'enabled',
            'nokwhmeter',
            'merkkwhmeter',
            'tahun_buat',
            'mcb',
            'type_pbts',
            'type_kotakapp',
            'latitudey',
            'longitudex',
            'namagd',
            'jenis_kabel',
            'ukuran_kabel',
            'ket_survey',
            'deret',
            'sr',
            'ket_validasi',
            'foto_kwh',
            'foto_bangunan'

        ];

        $exampleObjectId = 'objectid_dari_file_csv';
        $exampleIdpel = 'idpel_dari_file_csv';

        $exampleRow = [
            'objectid' => $exampleObjectId,
            'idpel' => $exampleIdpel,
            'user_pendataan' => 'nama_user',
            'enabled' => '1',
            // ... (isi kolom lain dengan contoh jika perlu, atau biarkan kosong)
            'nokwhmeter' => '',
            'merkkwhmeter' => '',
            'tahun_buat' => '',
            'mcb' => '',
            'type_pbts' => '',
            'type_kotakapp' => '',
            'latitudey' => '',
            'longitudex' => '',
            'namagd' => '',
            'jenis_kabel' => '',
            'ukuran_kabel' => '',
            'ket_survey' => '',
            'deret' => '',
            'sr' => '',
            'ket_validasi' => '',

            'foto_kwh' => '**="mapping_photos/unverified/" & B2 & "/" & TEXTJOIN("_"; TRUE; A2; B2; "foto_app")**',
            'foto_bangunan' => '**="mapping_photos/unverified/" & B2 & "/" & TEXTJOIN("_"; TRUE; A2; B2; "foto_persil")**',
        ];

        $callback = function () use ($columns, $exampleRow) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns, ';'); // Gunakan delimiter ';' sesuai importer
            fputcsv($file, array_values($exampleRow), ';');
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    public function create()
    {
        return view('team.mapping-kddk.partials.create');
    }

    const MANUAL_ID_OFFSET = 2000000; // Mulai ID manual dari 10 Juta + 1

    public function store(Request $request)
    {
        // 1. AMBIL SETTING DARI DB (Dalam MB)
        $maxMb = \App\Models\AppSetting::findValue('system_max_upload_mb', null, 5);
        // Konversi ke Kilobyte untuk Validator Laravel
        $maxKb = $maxMb * 1024;

        // 2. Validasi Data (user_pendataan tidak perlu divalidasi dari input)
        $validator = Validator::make($request->all(), [
            'idpel'         => 'required|string|max:12',
            'foto_kwh'      => 'required|string',
            'foto_bangunan' => 'required|string',
            'ket_survey'    => 'required|string',
            'latitudey'     => ['required', 'numeric', 'between:-90,90'],
            'longitudex'    => ['required', 'numeric', 'between:-180,180'],
        ], [
            // Pesan error kustom yang lebih ramah pengguna
            'latitudey.numeric' => 'Latitude harus berupa angka.',
            'latitudey.between' => 'Nilai Latitude harus di antara -90 dan 90.',
            'longitudex.numeric' => 'Longitude harus berupa angka.',
            'longitudex.between' => 'Nilai Longitude harus di antara -180 dan 180.',
        ]);

        if ($validator->fails()) {
            // Jika validasi gagal, coba hapus file sementara
            if ($request->filled('foto_kwh')) Storage::disk('local')->delete('temp_photos/' . basename($request->input('foto_kwh')));
            if ($request->filled('foto_bangunan')) Storage::disk('local')->delete('temp_photos/' . basename($request->input('foto_bangunan')));
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['user_pendataan'] = Auth::user()->name;
        $idpel = $validatedData['idpel'];

        // Cek Spamming (1 Menit)
        $isSpamming = MappingKddk::where('idpel', $idpel)
            ->where('user_pendataan', Auth::user()->name)
            ->where('ket_validasi', 'unverified')
            ->where('created_at', '>=', now()->subMinutes(1)) // Cek dalam 1 menit terakhir
            ->exists();

        if ($isSpamming) {
            return response()->json(['errors' => ['idpel' => ['Anda baru saja mengirim data untuk IDPEL ini. Mohon cek daftar data sebelum mengirim ulang.']]], 422);
        }

        // ============================================================
        // [FITUR BARU] SMART GEO-FENCING (VALIDASI WILAYAH)
        // ============================================================

        $warningMsg = "";

        // A. Cari UnitUP dari Pelanggan ini
        $customerInfo = MasterDataPelanggan::where('idpel', $idpel)->select('unitup')->first();

        if ($customerInfo && $customerInfo->unitup) {

            // B. Cari 1 Titik Referensi (Tetangga) di Unit yang sama SUDAH VERIFIED (MappingKddk)
            $neighbors = MappingKddk::join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->where('master_data_pelanggan.unitup', $customerInfo->unitup)
                ->where('mapping_kddk.enabled', true)
                ->whereNotNull('mapping_kddk.latitudey')
                ->where('mapping_kddk.idpel', '!=', $idpel) // Jangan bandingkan dengan diri sendiri
                ->select('mapping_kddk.latitudey', 'mapping_kddk.longitudex')
                ->inRandomOrder()
                ->limit(10)
                ->get();

            // C. Jika ada tentangga se-unit, kita hitung jaraknya
            if ($neighbors->isNotEmpty()) {
                $minDistance = null;
                $lat1 = (float) $validatedData['latitudey'];
                $lon1 = (float) $validatedData['longitudex'];

                // C. Loop cari yang terdekat
                foreach ($neighbors as $neighbor) {
                    $lat2 = (float) $neighbor->latitudey;
                    $lon2 = (float) $neighbor->longitudex;

                    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
                        $distMeter = 0;
                    } else {
                        $theta = $lon1 - $lon2;
                        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
                        $dist = acos($dist);
                        $dist = rad2deg($dist);
                        $miles = $dist * 60 * 1.1515;
                        $distMeter = $miles * 1609.344;
                    }

                    if (is_null($minDistance) || $distMeter < $minDistance) {
                        $minDistance = $distMeter;
                    }
                }
                // Cek Batas Toleransi (Default 5KM)
                if (!is_null($minDistance)) {
                    $limitMeter = \App\Models\AppSetting::findValue('kddk_anomaly_distance', null, 5000);

                    if ($minDistance > $limitMeter) {
                        $km = round($minDistance / 1000, 1);
                        $warningMsg = " (Info: Lokasi terpaut cukup jauh dari tetangga terdekat: {$km} KM)";
                    }
                }
            }
        }

        // --- LOGIKA STATUS: FIRST-COME FIRST-SERVED ---
        $existingCount = MappingKddk::where('idpel', $idpel)->count();
        $statusValidasi = ($existingCount == 0) ? 'verified' : 'unverified';
        $isEnabled = ($existingCount == 0) ? true : false;

        $finalPaths = [];
        $tempFilesToDelete = [];
        $newObjectId = null;
        $errors = []; // Tampung error     

        DB::beginTransaction();
        try {
            // 1. Generate Object ID Baru
            DB::table('objectid_sequence')->insert(['created_at' => now()]);
            $sequenceId = DB::getPdo()->lastInsertId();
            $newObjectId = $sequenceId + self::MANUAL_ID_OFFSET;
            $validatedData['objectid'] = $newObjectId;

            // 2. [LOGIC BARU] ENFORCE HISTORY LIMIT (Hapus data lama sebelum simpan baru)
            $this->enforceHistoryLimit($idpel, 2);

            // 3. Pindahkan Foto
            foreach (['foto_kwh', 'foto_bangunan'] as $photoType) {
                $tempFilename = $validatedData[$photoType];
                $tempPathRelative = 'temp_photos/' . basename($tempFilename);
                $tempFilesToDelete[] = $tempPathRelative;

                if (Storage::disk('local')->exists($tempPathRelative)) {
                    $extension = pathinfo($tempFilename, PATHINFO_EXTENSION);
                    // Masuk folder 'verified' jika data pertama, 'unverified' jika susulan
                    $folderTujuan = ($statusValidasi === 'verified') ? 'verified' : 'unverified';

                    if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png'])) {
                        throw new \Exception("Ekstensi file '{$tempFilename}' tidak valid.");
                    }

                    $suffix = ($photoType === 'foto_kwh' ? 'foto_app' : 'foto_persil');
                    $newFilename = "{$newObjectId}_{$idpel}_{$suffix}.{$extension}";

                    $finalRelativePath = "mapping_photos/{$folderTujuan}/{$idpel}/{$newFilename}";

                    // Pindah File
                    $fileContent = Storage::disk('local')->get($tempPathRelative);

                    if ($fileContent === false) {
                        throw new \Exception("Gagal membaca file sementara: $tempPathRelative");
                    }

                    if (!Storage::disk('public')->put($finalRelativePath, $fileContent)) {
                        throw new \Exception("Gagal menyimpan file ke public: $finalRelativePath");
                    }

                    $finalPaths[$photoType] = $finalRelativePath;
                } else {
                    throw new \Exception("File foto tidak ditemukan (Session expired?). Silakan upload ulang.");
                }
            }

            // 4. Siapkan Data Final untuk MappingKddk
            unset($validatedData['foto_kwh'], $validatedData['foto_bangunan']);
            $finalData = array_merge($validatedData, $finalPaths);

            // Set Status
            $finalData['ket_validasi'] = $statusValidasi;
            $finalData['enabled'] = $isEnabled;

            MappingKddk::create($finalData);

            DB::commit();

            // Cleanup
            foreach ($tempFilesToDelete as $tempPath) {
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
            }

            $msg = ($isEnabled)
                ? 'Data PERTAMA berhasil disimpan dan AKTIF di Peta!'
                : 'Data berhasil disimpan sebagai DRAFT (Menunggu Validasi/Promote).';

            if ($warningMsg) {
                $msg .= $warningMsg;
            }

            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Exception $e) {

            // 7. Rollback jika gagal
            DB::rollBack();
            if (isset($finalPaths['foto_kwh'])) Storage::disk('public')->delete($finalPaths['foto_kwh']);
            if (isset($finalPaths['foto_bangunan'])) Storage::disk('public')->delete($finalPaths['foto_bangunan']);

            Log::error("Store Mapping Error:: " . $e->getMessage());
            return response()->json(['errors' => ['server' => [substr($e->getMessage(), 0, 200)]]], 422);
        }
    }

    /**
     * Helper: Batasi histori.
     * UPDATE: Jangan pernah menghapus data yang 'enabled' (Data Master di Peta).
     */
    private function enforceHistoryLimit($idpel, $keepLimit = 2)
    {
        $histories = MappingKddk::where('idpel', $idpel)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($histories->count() > $keepLimit) {
            $deletedCount = 0;

            foreach ($histories->reverse() as $record) {
                if ($histories->count() - $deletedCount <= $keepLimit) break;

                // [SAFETY UPDATE]
                // 1. Jangan hapus data yang tampil di peta (enabled=true)
                // 2. Jangan hapus data yang verified (valid)
                if ($record->enabled || $record->ket_validasi === 'verified') {
                    continue;
                }

                // Hapus Fisik & DB
                if ($record->foto_kwh && Storage::disk('public')->exists($record->foto_kwh)) {
                    Storage::disk('public')->delete($record->foto_kwh);
                }
                if ($record->foto_bangunan && Storage::disk('public')->exists($record->foto_bangunan)) {
                    Storage::disk('public')->delete($record->foto_bangunan);
                }

                $record->delete();
                $deletedCount++;
                Log::info("Auto-cleanup: Menghapus draft lama IDPEL {$idpel}.");
            }
        }
    }

    public function uploadTemporaryPhoto(Request $request)
    {
        // Validasi file yang masuk
        $maxMb = \App\Models\AppSetting::findValue('system_max_upload_mb', null, 5);
        $maxKb = $maxMb * 1024;
        $validated = $request->validate([
            'photo' => "required|image|mimes:jpg,jpeg,png|max:{$maxKb}",
        ], [
            'photo.mimes' => 'Format file harus JPG, JPEG, atau PNG.',
            'photo.max' => "Ukuran file maksimal {$maxMb} MB.",
            'photo.image' => 'File harus berupa gambar.',
        ]);

        $photo = $validated['photo'];

        // --- LOG TAMBAHAN SEBELUM SIMPAN ---
        $originalName = $photo->getClientOriginalName();
        $originalExtension = $photo->getClientOriginalExtension();
        $guessedExtension = $photo->guessClientExtension();
        $mimeType = $photo->getMimeType();

        Log::debug('Upload Temporary (Before Store) - File Details:', [
            'original_name' => $originalName,
            'original_ext' => $originalExtension,
            'guessed_ext' => $guessedExtension,
            'mime_type' => $mimeType
        ]);
        // --- AKHIR LOG ---

        try {
            // Simpan file di folder sementara ('temp_photos') di disk 'local'
            // store() akan generate nama unik + mempertahankan ekstensi asli
            $path = $photo->store('temp_photos', 'local'); // <-- Lebih eksplisit pakai 'local'

            if ($path) {
                $savedFilename = basename($path);
                Log::info('Upload Temporary (After Store) - File Saved:', ['saved_path' => $path, 'saved_filename' => $savedFilename]);
                // Kembalikan hanya nama file unik (termasuk ekstensi)
                return response()->json(['filename' => $savedFilename]);
            } else {
                throw new \Exception("Fungsi store() mengembalikan path kosong.");
            }
        } catch (\Exception $e) {
            Log::error('Upload Temporary Photo Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengupload foto: ' . $e->getMessage()], 500);
        }
    }

    public function deleteTemporaryPhoto(Request $request)
    {
        $validated = $request->validate(['filename' => 'required|string']);
        $path = 'temp_photos/' . $validated['filename'];

        // Lakukan pengecekan keamanan dasar untuk mencegah penghapusan file di luar folder temp
        if (Storage::exists($path) && strpos(realpath(Storage::path($path)), realpath(Storage::path('temp_photos'))) === 0) {
            Storage::delete($path);
            return response()->json(['message' => 'File sementara berhasil dihapus.']);
        }

        return response()->json(['message' => 'File tidak ditemukan atau tidak valid.'], 404);
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        // Cari data berdasarkan ID
        $mapping = MappingKddk::findOrFail($id);

        // Return view edit dengan membawa data mapping
        // Pastikan path view sesuai dengan struktur folder Bapak
        return view('team.mapping-kddk.partials.edit', compact('mapping'));
    }

    public function update(Request $request, string $id)
    {
        $mapping = MappingKddk::findOrFail($id);

        // User harus melakukan 'Invalidate' dulu jika ingin mengubah data ini.
        if ($mapping->enabled == true || $mapping->ket_validasi === 'verified') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Data VERIFIED terkunci. Silakan "Tarik Kembali" (Invalidate) data ini terlebih dahulu untuk melakukan revisi.'], 403);
            }
            return back()->with('error', 'Data VERIFIED terkunci. Silakan "Tarik Kembali" data ini terlebih dahulu.');
        }

        // 1. Validasi Input
        // AMBIL SETTING DINAMIS
        $maxMb = \App\Models\AppSetting::findValue('system_max_upload_mb', null, 5);
        $maxKb = $maxMb * 1024;
        $validator = Validator::make($request->all(), [
            'latitudey'     => ['required', 'numeric', 'between:-90,90'],
            'longitudex'    => ['required', 'numeric', 'between:-180,180'],
            'ket_survey'    => 'required|string',
            'foto_kwh_input' => "nullable|image|max:{$maxKb}",
            'foto_bangunan_input' => "nullable|image|max:{$maxKb}",
        ], [
            'foto_kwh_input.max' => "Foto KWH maksimal {$maxMb} MB.",
            'foto_bangunan_input.max' => "Foto Bangunan maksimal {$maxMb} MB.",
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Array penampung file untuk cleanup
        $filesToDeleteOnCommit = [];   // File lama (hapus jika sukses)
        $filesToDeleteOnRollback = []; // File baru (hapus jika gagal)

        DB::beginTransaction();
        try {
            $dataToUpdate = [
                'latitudey' => $request->latitudey,
                'longitudex' => $request->longitudex,
                'ket_survey' => $request->ket_survey,
                'user_pendataan' => Auth::user()->name,
            ];

            // Pastikan ObjectID dan IDPEL ada untuk penamaan
            $objectId = $mapping->objectid ?? 'UNKNOWN';
            $idpel = $mapping->idpel;

            // --- LOGIKA UPDATE FOTO KWH ---
            if ($request->hasFile('foto_kwh_input')) {
                $file = $request->file('foto_kwh_input');
                $ext = $file->getClientOriginalExtension();

                // Format Nama: OBJECTID_IDPEL_foto_app.ext
                $filename = "{$objectId}_{$idpel}_foto_app.{$ext}";

                // Folder: Tetap di verified karena ini tabel MappingKddk
                $path = "mapping_photos/verified/{$idpel}/{$filename}";

                // 1. Simpan File Baru Dulu (Overwrite jika nama sama tidak masalah, karena konten terganti)
                // Gunakan 'putFileAs' atau 'put' dengan konten
                Storage::disk('public')->put($path, file_get_contents($file));

                // Catat file baru ini untuk dihapus jika nanti DB error
                $filesToDeleteOnRollback[] = $path;

                // Catat file lama untuk dihapus NANTI setelah commit sukses
                // Cek apakah file lama ada & path-nya beda (atau sama)
                if ($mapping->foto_kwh && Storage::disk('public')->exists($mapping->foto_kwh)) {
                    // PENTING: Jika nama file baru == nama file lama, jangan masukkan ke list hapus!
                    // Karena file lama sudah tertimpa oleh file baru di baris Storage::put di atas.
                    if ($mapping->foto_kwh !== $path) {
                        $filesToDeleteOnCommit[] = $mapping->foto_kwh;
                    }
                }

                $dataToUpdate['foto_kwh'] = $path;
            }

            // --- LOGIKA UPDATE FOTO BANGUNAN ---
            if ($request->hasFile('foto_bangunan_input')) {
                $file = $request->file('foto_bangunan_input');
                $ext = $file->getClientOriginalExtension();

                $filename = "{$objectId}_{$idpel}_foto_persil.{$ext}";
                $path = "mapping_photos/verified/{$idpel}/{$filename}";

                Storage::disk('public')->put($path, file_get_contents($file));
                $filesToDeleteOnRollback[] = $path;

                if ($mapping->foto_bangunan && Storage::disk('public')->exists($mapping->foto_bangunan)) {
                    if ($mapping->foto_bangunan !== $path) {
                        $filesToDeleteOnCommit[] = $mapping->foto_bangunan;
                    }
                }

                $dataToUpdate['foto_bangunan'] = $path;
            }

            // Simpan Update ke Database
            $mapping->update($dataToUpdate);

            DB::commit();

            // --- SUKSES: BERSIH-BERSIH FILE LAMA ---
            foreach ($filesToDeleteOnCommit as $oldFile) {
                if (Storage::disk('public')->exists($oldFile)) {
                    Storage::disk('public')->delete($oldFile);
                }
            }

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui!']);
            }
            return redirect()->route('team.mapping.index')->with('success', 'Data berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();

            // --- GAGAL: HAPUS FILE BARU YANG TERLANJUR DIUPLOAD ---
            foreach ($filesToDeleteOnRollback as $newFile) {
                if (Storage::disk('public')->exists($newFile)) {
                    Storage::disk('public')->delete($newFile);
                }
            }

            Log::error("Gagal update mapping ID {$id}: " . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['errors' => ['server' => [$e->getMessage()]]], 500);
            }
            return back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        //
    }

    public function promoteToValid(Request $request, $id)
    {
        // Gunakan lockForUpdate agar aman dari race condition saat promote
        $dataToPromote = MappingKddk::where('id', $id)->lockForUpdate()->firstOrFail();
        $idpel = $dataToPromote->idpel;

        DB::beginTransaction();
        try {

            $updatedPaths = [];

            // 1. PINDAHKAN FILE FISIK (Unverified -> Verified)
            foreach (['foto_kwh', 'foto_bangunan'] as $field) {
                $oldPath = $dataToPromote->$field;

                // Cek apakah file ada dan berada di folder unverified
                if ($oldPath && strpos($oldPath, 'unverified') !== false && Storage::disk('public')->exists($oldPath)) {
                    $newPath = str_replace('unverified', 'verified', $oldPath);

                    // Pastikan folder tujuan ada
                    $directory = dirname($newPath);
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory);
                    }

                    // Pindahkan file
                    if (Storage::disk('public')->move($oldPath, $newPath)) {
                        $updatedPaths[$field] = $newPath;
                    }
                }
            }

            // 2. Nonaktifkan (Supersede) data LAMA yang sedang aktif
            MappingKddk::where('idpel', $idpel)
                ->where('id', '!=', $id)
                ->where(function ($query) {
                    $query->where('enabled', true)
                        ->orWhere('ket_validasi', 'verified');
                })
                ->update([
                    'enabled' => false,
                    'ket_validasi' => 'superseded'
                ]);

            // 3. Aktifkan (Promosikan) data BARU
            $dataToPromote->enabled = true;
            $dataToPromote->ket_validasi = 'verified';

            // Update path foto baru (jika ada yang berpindah)
            if (!empty($updatedPaths)) {
                $dataToPromote->fill($updatedPaths);
            }

            $dataToPromote->save();

            // 4. Bersihkan Histori Sampah (PENTING)
            // Karena sekarang sudah ada master baru, kita buang draft/sampah lama yang berlebih
            $this->enforceHistoryLimit($idpel, 2);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Data Object Id ' . $dataToPromote->objectid . ' berhasil ditetapkan sebagai data aktif.'], 200);
            }

            return back()->with('success', 'Data Object Id ' . $dataToPromote->objectid . ' berhasil ditetapkan sebagai data aktif.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal promote data ID {$id}: " . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Gagal memproses: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }

    public function invalidate(Request $request, $id)
    {
        $user = Auth::user();
        $reason = $request->input('reason');
        if (empty($reason)) {
            $reason = 'Data ditarik kembali oleh ' . $user->name . ' untuk validasi ulang.';
        }

        DB::beginTransaction();
        try {

            // 1. LOCK DATA SUMBER
            $validData = MappingKddk::where('id', $id)->lockForUpdate()->first();

            if (!$validData) {
                DB::rollBack();
                return back()->with('error', 'Data tidak ditemukan atau sudah diproses oleh user lain.');
            }

            // 2. PINDAHKAN FILE FISIK (Verified -> Unverified)
            $pathsToUpdate = [];
            $oldKwhPath = $validData->foto_kwh;
            $oldBangunanPath = $validData->foto_bangunan;
            $newKwhPath = null;
            $newBangunanPath = null;

            $tempDataArray = $validData->toArray();

            // 1. PINDAHKAN FILE BALIK KE UNVERIFIED
            if ($oldKwhPath && strpos($oldKwhPath, 'verified') !== false && Storage::disk('public')->exists($oldKwhPath)) {
                $newKwhPath = str_replace('verified', 'unverified', $oldKwhPath);
                $dir = dirname($newKwhPath);
                if (!Storage::disk('public')->exists($dir)) Storage::disk('public')->makeDirectory($dir);
                if (!Storage::disk('public')->move($oldKwhPath, $newKwhPath)) throw new \Exception("Gagal pindah file KWH.");
                $pathsToUpdate['foto_kwh'] = $newKwhPath;
            }

            if ($oldBangunanPath && strpos($oldBangunanPath, 'verified') !== false && Storage::disk('public')->exists($oldBangunanPath)) {
                $newBangunanPath = str_replace('verified', 'unverified', $oldBangunanPath);
                $dir = dirname($newBangunanPath);
                if (!Storage::disk('public')->exists($dir)) Storage::disk('public')->makeDirectory($dir);
                if (!Storage::disk('public')->move($oldBangunanPath, $newBangunanPath)) throw new \Exception("Gagal pindah file Bangunan.");
                $pathsToUpdate['foto_bangunan'] = $newBangunanPath;
            }

            // 2. UPDATE STATUS (SINGLE TABLE - NO DELETE)
            $updateData = [
                'ket_validasi'     => 'recalled', // Status turun
                'enabled'          => false,      // Hilang dari peta
                'validation_notes' => $reason,    // Catatan alasan
                'validation_data'  => null,       // Reset info validasi
                'locked_by'        => null,
                'locked_at'        => null
            ];

            // Gabungkan dengan path foto baru (jika ada yang berubah)
            if (!empty($pathsToUpdate)) {
                $updateData = array_merge($updateData, $pathsToUpdate);
            }

            // Eksekusi Update
            $validData->update($updateData);

            DB::commit();

            $msg = 'Data berhasil ditarik kembali (Revisi). Status kembali ke Draft.';

            if ($request->expectsJson()) return response()->json(['message' => $msg], 200);
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal invalidate data KDDK ID {$id}: " . $e->getMessage());

            // Rollback pemindahan file jika gagal
            if ($newKwhPath && Storage::disk('public')->exists($newKwhPath)) {
                Storage::disk('public')->move($newKwhPath, $oldKwhPath);
            }
            if ($newBangunanPath && Storage::disk('public')->exists($newBangunanPath)) {
                Storage::disk('public')->move($newBangunanPath, $oldBangunanPath);
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Gagal menarik data: ' . $e->getMessage()], 500);
            }

            return back()->with('error', 'Gagal menarik data: ' . $e->getMessage());
        }
    }

    public function processCoordinateRequest(Request $request)
    {
        try {

            $this->cleanupOldTempFiles();
            
            // 1. Validasi Input
            $request->validate([
                'file_idpel' => 'required|file|mimes:csv,txt|max:2048',
            ]);

            // 2. Baca File
            $path = $request->file('file_idpel')->getRealPath();
            $rows = array_map('str_getcsv', file($path));

            $requestedIdpels = [];
            foreach ($rows as $row) {
                if (isset($row[0])) {
                    $cleanId = trim($row[0]);
                    if (is_numeric($cleanId) && strlen($cleanId) >= 10) {
                        $requestedIdpels[] = $cleanId;
                    }
                }
            }

            $limit = 1000;
            if (count($requestedIdpels) > $limit) throw new \Exception("Maksimal $limit IDPEL.");
            if (empty($requestedIdpels)) throw new \Exception("File kosong atau tidak valid.");

            // 3. Query Database (SAFE MODE)
            $results = MappingKddk::whereIn('mapping_kddk.idpel', $requestedIdpels)
                ->where('mapping_kddk.enabled', true)
                ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->select(
                    'mapping_kddk.idpel',
                    'mapping_kddk.latitudey',
                    'mapping_kddk.longitudex',
                    'master_data_pelanggan.nama_gardu',
                    'master_data_pelanggan.tarif',
                    'master_data_pelanggan.daya'
                )
                ->get()
                ->keyBy('idpel');

            // 4. Susun Data CSV (FORMAT ANTI-ERROR EXCEL)
            // Header
            $csvContent = "IDPEL;NAMAGD;TARIF;DAYA;LATITUDE;LONGITUDE;STATUS\n";

            $foundCount = 0;
            $notFoundCount = 0;

            foreach ($requestedIdpels as $id) {
                if (isset($results[$id])) {
                    $r = $results[$id];

                    // TRICK: Gunakan ="VALUE" agar Excel tidak mengubah format angka
                    // Contoh: ="101.444" akan tetap 101.444, tidak jadi 101444
                    $lat = $r->latitudey;
                    $lng = $r->longitudex;

                    $csvContent .= "=\"{$id}\";\"{$r->nama_gardu}\";\"{$r->tarif}\";\"{$r->daya}\";=\"{$lat}\";=\"{$lng}\";\"Data Found\"\n";

                    $foundCount++;
                } else {
                    $csvContent .= "=\"{$id}\";\"-\";\"-\";\"-\";\"0\";\"0\";\"Data Not Found\"\n";
                    $notFoundCount++;
                }
            }

            // 5. Simpan File
            $filename = 'REQ_' . time() . '_' . Auth::id() . '.csv';
            Storage::disk('local')->put('temp_exports/' . $filename, $csvContent);

            return response()->json([
                'success' => true,
                'stats' => ['total' => count($requestedIdpels), 'found' => $foundCount, 'not_found' => $notFoundCount],
                'download_url' => route('team.mapping.download_request_result', ['file' => $filename])
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function downloadRequestResult(Request $request)
    {
        $filename = $request->input('file');

        // Validasi Nama File
        if (!$filename || basename($filename) !== $filename) abort(404);

        $filePath = 'temp_exports/' . $filename;

        // Cek Keberadaan File via Storage Facade (Sama persis dengan saat menyimpan)
        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'File tidak ditemukan atau sudah kadaluarsa.');
        }

        // Bersihkan Output Buffer (Penting agar file tidak corrupt)
        if (ob_get_length()) ob_end_clean();

        // Download menggunakan Storage Facade
        // deleteFileAfterSend tidak support langsung di Storage::download pada beberapa versi Laravel,
        // jadi kita download file path penuhnya.
        return response()->download(Storage::disk('local')->path($filePath))->deleteFileAfterSend(true);
    }

    private function cleanupOldTempFiles()
    {
        try {
            // Ambil semua file di folder temp
            $files = Storage::disk('local')->files('temp_exports');

            // Waktu sekarang
            $now = now();

            foreach ($files as $file) {
                // Ambil waktu terakhir file dimodifikasi
                $lastModified = Storage::disk('local')->lastModified($file);
                $fileTime = \Carbon\Carbon::createFromTimestamp($lastModified);

                // Jika umur file > 60 menit, HAPUS!
                if ($now->diffInMinutes($fileTime) > 60) {
                    Storage::disk('local')->delete($file);
                }
            }
        } catch (\Exception $e) {
            // Silent fail: Jangan sampai error bersih-bersih mengganggu proses utama
            Log::warning("Gagal membersihkan temp files: " . $e->getMessage());
        }
    }
}
