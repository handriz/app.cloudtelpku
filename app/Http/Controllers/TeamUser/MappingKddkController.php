<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use App\Models\MappingKddk;
use App\Models\TemporaryMapping; 
use App\Models\MasterDataPelanggan;
use App\Models\HierarchyLevel;
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
            ->select('mapping_kddk.*', 'master_data_pelanggan.unitup', 'master_data_pelanggan.unitap')
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
                    WHEN mapping_kddk.ket_validasi = 'recalled_1' THEN 3
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
                                WHEN 'recalled_1' THEN 3
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
                $rankingQuery->where(function($q) use ($search) {
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
        $mappings = $query->paginate(10)->withQueryString();

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
            // END PERBAIKAN FOKUS DATA ENABLE

            if ($searchedMapping) {
                $mappingStatus = ($searchedMapping->enabled) ? 'valid' : 'unverified';
                
            } elseif ($isIdpelSearch) {
                // Kasus jika IDPEL dicari tapi tidak ada hasil
                $mappingStatus = 'unverified';
            }
        }
        // 7. Hitung data untuk kartu ringkasan
        // (Logika ini tetap sama seperti yang Anda miliki sebelumnya)
        $totalPelanggan = MasterDataPelanggan::count();
        $totalMappingEnabled = MappingKddk::where('mapping_kddk.enabled', true)
            ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                return $query->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                             ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
            })
            ->count();
        $mappingPercentage = ($totalPelanggan > 0) ? ($totalMappingEnabled / $totalPelanggan) * 100 : 0;
        
        // 8. Siapkan semua data yang dibutuhkan oleh view
        $viewData = compact(
            'mappings', 'totalMappingEnabled', 'totalPelanggan', 'mappingPercentage',
            'search', 'sortColumn', 'sortDirection', 'mappingStatus', 'searchedIdpel', 'searchedMapping'
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
            ->inRandomOrder()
            ->limit(100)
            ->get();
            return response()->json(['searched' => [], 'nearby' => [], 'all' => $initialCustomers]);
        }

        // SKENARIO 2: Ada Pencarian
        $searchedCustomers = (clone $baseQuery)
            ->select('mapping_kddk.idpel', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex', 'mapping_kddk.namagd')
            ->when($search, function ($query, $search) {
                return $query->where(function($q) use ($search) {
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
            'objectid','idpel', 'user_pendataan', 'enabled', 'nokwhmeter', 'merkkwhmeter',
            'tahun_buat', 'mcb', 'type_pbts', 'type_kotakapp', 'latitudey', 'longitudex',
            'namagd', 'jenis_kabel', 'ukuran_kabel', 'ket_survey', 'deret', 'sr',
            'ket_validasi', 'foto_kwh', 'foto_bangunan'
        
        ];
        
        $exampleObjectId = 'objectid_dari_file_csv';
        $exampleIdpel = 'idpel_dari_file_csv';

        $exampleRow = [
            'objectid' => $exampleObjectId,
            'idpel' => $exampleIdpel,
            'user_pendataan' => 'nama_user',
            'enabled' => '1',
            // ... (isi kolom lain dengan contoh jika perlu, atau biarkan kosong)
            'nokwhmeter' => '', 'merkkwhmeter' => '', 'tahun_buat' => '', 'mcb' => '', 
            'type_pbts' => '', 'type_kotakapp' => '', 'latitudey' => '', 'longitudex' => '', 
            'namagd' => '', 'jenis_kabel' => '', 'ukuran_kabel' => '', 'ket_survey' => '', 
            'deret' => '', 'sr' => '', 'ket_validasi' => '',

            'foto_kwh' => '**="mapping_photos/unverified/" & B2 & "/" & TEXTJOIN("_"; TRUE; A2; B2; "foto_app")**',
            'foto_bangunan' => '**="mapping_photos/unverified/" & B2 & "/" & TEXTJOIN("_"; TRUE; A2; B2; "foto_persil")**',
        ];

        $callback = function() use ($columns , $exampleRow) {
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
        // 1. Validasi Data (user_pendataan tidak perlu divalidasi dari input)
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

        // ============================================================
        // [FITUR BARU] SMART GEO-FENCING (VALIDASI WILAYAH)
        // ============================================================
        
        // A. Cari UnitUP dari Pelanggan ini
        $customerInfo = MasterDataPelanggan::where('idpel', $idpel)->select('unitup')->first();
        if ($customerInfo && $customerInfo->unitup) {
            // B. Cari 1 Titik Referensi (Tetangga) di Unit yang sama
            // Kita ambil data mapping yang SUDAH VERIFIED (MappingKddk)
            $referencePoint = MappingKddk::join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                ->where('master_data_pelanggan.unitup', $customerInfo->unitup)
                ->where('mapping_kddk.enabled', true) // Hanya yang aktif
                ->whereNotNull('mapping_kddk.latitudey')
                ->where('mapping_kddk.idpel', '!=', $idpel) // Jangan bandingkan dengan diri sendiri
                ->select('mapping_kddk.latitudey', 'mapping_kddk.longitudex')
                ->inRandomOrder() // Ambil acak biar representatif
                ->first();

            // C. Jika ada teman se-unit, kita hitung jaraknya
            if ($referencePoint) {
                $lat1 = (float) $validatedData['latitudey'];
                $lon1 = (float) $validatedData['longitudex'];
                $lat2 = (float) $referencePoint->latitudey;
                $lon2 = (float) $referencePoint->longitudex;

                // Rumus Haversine Sederhana (Jarak dalam KM)
                $theta = $lon1 - $lon2;
                $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
                $dist = acos($dist);
                $dist = rad2deg($dist);
                $miles = $dist * 60 * 1.1515;
                $km = $miles * 1.609344;

                // D. Tentukan Batas Toleransi (Misal: 50 KM)
                // Jika input > 50 KM dari teman se-unitnya, kemungkinan besar SALAH INPUT.
                if ($km > 50) {
                    // Hapus file temp karena validasi gagal
                    if ($request->filled('foto_kwh')) Storage::disk('local')->delete('temp_photos/' . basename($request->input('foto_kwh')));
                    if ($request->filled('foto_bangunan')) Storage::disk('local')->delete('temp_photos/' . basename($request->input('foto_bangunan')));

                    return response()->json([
                        'errors' => [
                            'latitudey' => [
                                "Koordinat mencurigakan! Titik ini berjarak " . round($km) . " KM dari pusat data unit {$customerInfo->unitup}. Mohon cek kembali Latitude/Longitude Anda."
                            ]
                        ]
                    ], 422);
                }
            }
        }
        // ============================================================
        // [AKHIR FITUR BARU]
        // ============================================================
        
        $finalPaths = [];
        $tempFilesToDelete = [];
        $newObjectId = null; // Inisialisasi  
        $errors = []; // Tampung error

        DB::beginTransaction();
        try {
            DB::table('objectid_sequence')->insert(['created_at' => now()]);
            $sequenceId = DB::getPdo()->lastInsertId();

            $newObjectId = $sequenceId + self::MANUAL_ID_OFFSET;

            $validatedData['objectid'] = $newObjectId;

            foreach (['foto_kwh', 'foto_bangunan'] as $photoType) {
                $tempFilename = $validatedData[$photoType];
                $tempPathRelative = 'temp_photos/' . basename($tempFilename);
                $tempFilesToDelete[] = $tempPathRelative;

                if (Storage::disk('local')->exists($tempPathRelative)) {
                    $extension = pathinfo($tempFilename, PATHINFO_EXTENSION);
                    if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png'])) {
                        throw new \Exception("Ekstensi file '{$tempFilename}' tidak valid.");
                    }
                    $newFilename = $newObjectId . '_' . $idpel . '_' . ($photoType === 'foto_kwh' ? 'foto_app' : 'foto_persil') . '.' . $extension;
                    $finalRelativePath = "mapping_photos/unverified/{$idpel}/{$newFilename}";

                    $fileContent = Storage::disk('local')->get($tempPathRelative);
                    if ($fileContent === false) throw new \Exception("Gagal membaca file sementara: $tempPathRelative");

                    if (!Storage::disk('public')->put($finalRelativePath, $fileContent)) throw new \Exception("Gagal menyimpan file: $finalRelativePath");

                    $finalPaths[$photoType] = $finalRelativePath;
                } else {
                    throw new \Exception("File sementara '{$tempFilename}' tidak ditemukan.");
                }
            }
            unset($validatedData['foto_kwh'], $validatedData['foto_bangunan']);
            $finalData = array_merge($validatedData, $finalPaths);
            TemporaryMapping::create($finalData);

            DB::commit();

            foreach($tempFilesToDelete as $tempPath) {
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
            }
            return response()->json(['success' => true,'message' => 'Data mapping berhasil ditambahkan!']);

        } catch (\Exception $e) {
            // 7. Rollback jika gagal
            DB::rollBack();
            if (isset($finalPaths['foto_kwh'])) Storage::disk('public')->delete($finalPaths['foto_kwh']);
            if (isset($finalPaths['foto_bangunan'])) Storage::disk('public')->delete($finalPaths['foto_bangunan']);

            Log::error("Gagal store mapping KDDK (Controller): " . $e->getMessage());
            return response()->json(['errors' => ['server' => [substr($e->getMessage(), 0, 200)]]], 422);
        }            
    }

    public function uploadTemporaryPhoto(Request $request)
    {
        // Validasi file yang masuk
        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ],[
            'photo.mimes' => 'Format file harus JPG, JPEG, atau PNG.',
            'photo.max' => 'Ukuran file maksimal 2MB.',
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
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }

    public function invalidate(Request $request, $id)
    {
        $user = Auth::user();
        // Ambil alasan dari input, atau buat default
        $reason = $request->input('reason');
        if (empty($reason)) {
            $reason = 'Data ditarik kembali oleh ' . $user->name . ' untuk validasi ulang.';
        }

        DB::beginTransaction();
        try {

            // [PERBAIKAN 1] LOCKING SUMBER DATA
            // Gunakan lockForUpdate() agar proses lain tidak bisa membaca baris ini sampai transaksi selesai.
            // Jika data sudah dihapus proses lain, ini akan error atau return null (aman).
            $validData = MappingKddk::where('id', $id)->lockForUpdate()->first();

            if (!$validData) {
                DB::rollBack(); // Batalkan jika data ternyata sudah hilang/diproses orang lain
                return back()->with('error', 'Data tidak ditemukan atau sudah diproses oleh user lain.');
            }

            $idpel = $validData->idpel;
            $objectid = $validData->objectid;

            // 2. Salin data ke array untuk tabel temporer
            $tempDataArray = $validData->toArray();

            // 3. Pindahkan foto dari 'verified' kembali ke 'unverified'
            $oldKwhPath = $validData->foto_kwh;
            $oldBangunanPath = $validData->foto_bangunan;
            $newKwhPath = null; // Inisialisasi
            $newBangunanPath = null; // Inisialisasi

            // Cek file fisik sebelum pindah (mencegah error jika file hilang manual)
            if ($oldKwhPath && Storage::disk('public')->exists($oldKwhPath)) {
                $newKwhPath = str_replace('verified', 'unverified', $oldKwhPath);
                // Pastikan folder tujuan ada
                $dir = dirname($newKwhPath);
                if (!Storage::disk('public')->exists($dir)) Storage::disk('public')->makeDirectory($dir);
                Storage::disk('public')->move($oldKwhPath, $newKwhPath);
                $tempDataArray['foto_kwh'] = $newKwhPath;
            }
            
            if ($oldBangunanPath && Storage::disk('public')->exists($oldBangunanPath)) {
                $newBangunanPath = str_replace('verified', 'unverified', $oldBangunanPath);
                $dir = dirname($newBangunanPath);
                if (!Storage::disk('public')->exists($dir)) Storage::disk('public')->makeDirectory($dir);
                Storage::disk('public')->move($oldBangunanPath, $newBangunanPath);
                $tempDataArray['foto_bangunan'] = $newBangunanPath;
            }

            // 4. Hapus data yang tidak relevan & set status baru
            unset($tempDataArray['id'], $tempDataArray['created_at'], $tempDataArray['updated_at']);
            
            // --- Logika Status Baru ---
            // Kita set status baru sebagai "recalled" (ditarik)
            $tempDataArray['ket_validasi'] = 'recalled_1'; 
            $tempDataArray['enabled'] = false;
            $tempDataArray['validation_notes'] = $reason; 
            $tempDataArray['validation_data'] = null;   
            $tempDataArray['locked_by'] = null;     
            $tempDataArray['locked_at'] = null;

            // 5. GANTI LOGIKA: KUNCI BARIS & INSERT/UPDATE (Anti-Race Condition)
            // [PERBAIKAN 2] LOCKING TUJUAN (Anti-Race Condition di Antrian)
            $existingTemp = TemporaryMapping::where('objectid', $objectid)
                                            ->lockForUpdate() // <--- IMPLEMENTASI LOCKING
                                            ->first();
                                            
            if ($existingTemp) {
                // Jika baris sudah ada (mungkin Job sedang mengunci atau sudah ada di antrian), update saja
                $existingTemp->fill($tempDataArray);
                $existingTemp->save();
            } else {
                // Jika baris tidak ada, buat record baru di antrian
                TemporaryMapping::create($tempDataArray); 
            }

            // 6. Hapus data dari mapping_kddk (tabel utama)
            $validData->delete();

            // 7. Kembalikan status data "superseded" (jika ada) menjadi "verified" kembali
            // Agar histori data sebelumnya aktif lagi secara otomatis
            MappingKddk::where('idpel', $idpel)
                       ->where('ket_validasi', 'superseded')
                       ->orderByDesc('created_at')
                       ->limit(1)
                       ->update(['ket_validasi' => 'verified', 'enabled' => true]);

            DB::commit();

            if ($request->expectsJson()) {
                 return response()->json(['message' => 'Data IDPEL ' . $idpel . ' berhasil ditarik kembali ke antrian validasi. Data lama kini siap untuk dipromosikan ulang.'], 200);
            }

            return back()->with('success', 'Data IDPEL ' . $idpel . ' berhasil ditarik kembali ke antrian validasi.');

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

    public function promoteToValid(Request $request, $id)
    {
        $dataToPromote = MappingKddk::findOrFail($id);
        $idpel = $dataToPromote->idpel;

        DB::beginTransaction();
        try {
            // 1. Nonaktifkan (Supersede) data LAMA yang sedang aktif
            // Cari data dengan IDPEL yang sama & enabled = true
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

            // 2. Aktifkan (Promosikan) data BARU
            $dataToPromote->enabled = true;
            // Pastikan ket_validasi adalah 'verified' (atau 'valid' jika Anda mau, tapi 'verified' sudah cukup)
            $dataToPromote->ket_validasi = 'verified'; 
            $dataToPromote->save();

            DB::commit();
            if ($request->expectsJson()) {
                 return response()->json(['message' => 'Data Object ID ' . $dataToPromote->objectid . ' berhasil ditetapkan sebagai data aktif.'], 200);
            }

            return back()->with('success', 'Data Object ID ' . $dataToPromote->objectid . ' berhasil ditetapkan sebagai data aktif.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal promote data ID {$id}: " . $e->getMessage());
            if ($request->expectsJson()) {
                 return response()->json(['error' => 'Gagal memproses: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }
    
}
