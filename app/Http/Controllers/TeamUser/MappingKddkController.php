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
        
        } else {
            
            // SKENARIO 2: User mencari hal lain ATAU tidak mencari.
            // Tampilkan HANYA SATU record per IDPEL (yang terbaru).
            
            // Buat SubQuery untuk mendapatkan ID unik (terbaru) per grup IDPEL
            $subQuery = DB::table('mapping_kddk')->select(DB::raw('MAX(mapping_kddk.id) as id'))
                
                // SubQuery ini juga HARUS difilter berdasarkan hirarki
                ->when(!$user->hasRole('admin'), function ($q) use ($hierarchyFilter) {
                    $q->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                      ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
                });

            if ($search) {
                // Jika ini pencarian "Other" (bukan IDPEL), filter grup IDPEL
                $subQuery->where(function($q) use ($search) {
                    $q->where('mapping_kddk.idpel', 'like', "%{$search}%")
                      ->orWhere('mapping_kddk.nokwhmeter', 'like', "%{$search}%")
                      ->orWhere('mapping_kddk.user_pendataan', 'like', "%{$search}%");
                });
            }
            
            $subQuery->groupBy('mapping_kddk.idpel');
            
            // Query utama HANYA mengambil ID yang ada di hasil SubQuery
            $query->whereIn('mapping_kddk.id', $subQuery);
        }

        // 5. Terapkan sorting dan paginasi
        $query->orderBy($sortColumn, $sortDirection);
        // Terapkan paginasi ke query yang sudah lengkap
        $mappings = $query->paginate(15)->withQueryString();

        // 6. Siapkan Data Header (Foto, Peta, Status)
        // Ambil data dari hasil query utama, BUKAN query terpisah
       $searchedMapping = null;
        $mappingStatus = null;
        $searchedIdpel = null;
        
        if ($search) {
            $searchedMapping = $mappings->first();

            if ($searchedMapping) {
            $searchedIdpel = $searchedMapping->idpel;
            $mappingStatus = ($searchedMapping->ket_validasi === 'valid') ? 'valid' : 'unverified';
            } elseif ($isIdpelSearch) {
            // Kasus jika IDPEL dicari tapi tidak ada hasil (misal: IDPEL hanya ada di temporary)
            $searchedIdpel = $search;
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

        // Jika tidak ada pencarian, kembalikan semua data dalam satu grup 'all'
        if (!$search) {
            return response()->json(['all' => []]);
        }

        // Jika ADA pencarian, pisahkan logikanya
        $searchedCustomers = (clone $baseQuery)
            ->select('mapping_kddk.idpel', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex')
            ->when($search, function ($query, $search) {
                return $query->where('mapping_kddk.idpel', 'like', "%{$search}%")
                            ->orWhere('mapping_kddk.nokwhmeter', 'like', "%{$search}%");
            })->get();

        $nearbyCustomers = collect();

        if ($searchedCustomers->isNotEmpty()) {
            $centerPoint = $searchedCustomers->first();
            $lat = $centerPoint->latitudey;
            $lon = $centerPoint->longitudex;
            $radius = 0.1; // 100 meter

            $nearbyCustomers = (clone $baseQuery)
                ->select('mapping_kddk.idpel', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex')
                ->selectRaw("( 6371 * acos( cos( radians(?) ) * cos( radians( mapping_kddk.latitudey ) ) * cos( radians( mapping_kddk.longitudex ) - radians(?) ) + sin( radians(?) ) * sin( radians( mapping_kddk.latitudey ) ) ) ) AS distance", [$lat, $lon, $lat])
                ->having("distance", "<", $radius)
                ->whereIn('mapping_kddk.idpel', $searchedCustomers->pluck('idpel')->toArray(), 'and', true) // Not in searched
                ->orderBy("distance")
                ->limit(10)
                ->get();
        }

        return response()->json([
            'searched' => $searchedCustomers,
            'nearby' => $nearbyCustomers
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
        
        $exampleObjectId = '1';
        $exampleIdpel = '181405316052';

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

            'foto_kwh' => $exampleObjectId . '_' . $exampleIdpel . '_foto_app.jpg',
            'foto_bangunan' => $exampleObjectId . '_' . $exampleIdpel . '_foto_persil.jpg',
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

        // --- LOGIKA max() + 1 ---
        $lastTempId = TemporaryMapping::max('objectid');
        $lastKddkId = MappingKddk::max('objectid');
        $trueLastObjectId = max($lastTempId, $lastKddkId);
        $newObjectId = ($trueLastObjectId !== null ? $trueLastObjectId : 0) + 1;
        $validatedData['objectid'] = $newObjectId;
        $idpel = $validatedData['idpel'];

        // 3. Pindahkan file dari 'temp_photos' (local disk) ke 'public' disk
        $finalPaths = [];
        $errors = []; // Tampung error

        foreach (['foto_kwh', 'foto_bangunan'] as $photoType) {
            $tempFilename = $validatedData[$photoType]; 
            $tempPathRelative = 'temp_photos/' . basename($tempFilename); // Keamanan: basename

            Log::debug('Store (Check Extension) - Processing:', ['temp_filename' => $tempFilename]);

            if (Storage::disk('local')->exists($tempPathRelative)) {

                // --- LOG TAMBAHAN ---
                $detectedExtension = pathinfo($tempFilename, PATHINFO_EXTENSION);
                $tempMimeType = Storage::disk('local')->mimeType($tempPathRelative); // Cek tipe konten asli
                Log::debug('Store (Check Extension) - Details:', [
                    'detected_extension' => $detectedExtension,
                    'temp_mime_type' => $tempMimeType // Apakah ini image/jpeg atau image/png?
                ]);

                $extension = pathinfo($tempFilename, PATHINFO_EXTENSION);
                if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png'])) {
                    $errors[$photoType] = "Ekstensi file sementara '{$tempFilename}' tidak valid atau kosong.";
                    Log::warning($errors[$photoType]);
                    continue; // Lanjut ke foto berikutnya
                }

                // Buat nama file baru DENGAN ekstensi
                $newFilename = $newObjectId . '_' . $idpel . '_' . ($photoType === 'foto_kwh' ? 'foto_app' : 'foto_persil') . '.' . $extension;
                // Buat path relatif tujuan LENGKAP DENGAN ekstensi
                $finalRelativePath = "mapping_photos/unverified/{$idpel}/{$newFilename}";

                Log::debug('Store (Check Extension) - Saving as:', ['target_path' => $finalRelativePath]);

                try {
                    // Dapatkan konten file dari disk local
                    $fileContent = Storage::disk('local')->get($tempPathRelative);
                    if ($fileContent === false) {
                         throw new \Exception("Gagal membaca konten file sementara.");
                    }

                    // Simpan konten ke disk public
                    $putResult = Storage::disk('public')->put($finalRelativePath, $fileContent);
                    if (!$putResult) {
                         throw new \Exception("Operasi put() gagal disimpan ke disk public.");
                    }

                    // --- VERIFIKASI NAMA FILE FISIK ---
                    // Cek apakah file fisik BENAR-BENAR ada DENGAN ekstensi setelah disimpan
                    if (Storage::disk('public')->exists($finalRelativePath)) {
                        // File ditemukan dengan nama + ekstensi, ini BENAR
                        $finalPaths[$photoType] = $finalRelativePath; // Simpan path ini ke DB
                        Log::info('Store (Fix Attempt) - File saved successfully WITH extension:', ['path' => $finalRelativePath]);
                        Storage::disk('local')->delete($tempPathRelative); // Hapus file temp setelah sukses
                    } else {
                        // Jika tidak ditemukan DENGAN ekstensi, cek TANPA ekstensi
                        $pathWithoutExt = pathinfo($finalRelativePath, PATHINFO_DIRNAME) . '/' . pathinfo($finalRelativePath, PATHINFO_FILENAME);
                        if (Storage::disk('public')->exists($pathWithoutExt)) {
                            // Bug terdeteksi! File fisik disimpan tanpa ekstensi
                            Log::error("!!! BUG DETECTED in store !!! File fisik disimpan TANPA ekstensi:", ['expected' => $finalRelativePath, 'actual' => $pathWithoutExt]);
                            // Opsional: Coba rename file fisik agar memiliki ekstensi
                            try {
                                 Log::info("Mencoba rename file fisik tanpa ekstensi ke nama yang benar...");
                                 Storage::disk('public')->move($pathWithoutExt, $finalRelativePath);
                                 // Jika rename berhasil, kita lanjutkan seolah-olah sukses
                                 $finalPaths[$photoType] = $finalRelativePath;
                                 Log::info('Store (Fix Attempt) - Rename berhasil. File sekarang DENGAN ekstensi:', ['path' => $finalRelativePath]);
                                 Storage::disk('local')->delete($tempPathRelative); // Hapus file temp
                            } catch (\Exception $renameErr) {
                                 Log::error("Gagal me-rename file fisik tanpa ekstensi: " . $renameErr->getMessage());
                                 throw new \Exception("Bug terdeteksi: File fisik disimpan tanpa ekstensi oleh Storage::put() dan gagal di-rename.");
                            }
                        } else {
                            // File tidak ditemukan sama sekali setelah put()
                            throw new \Exception("File tidak ditemukan di disk 'public' setelah put(), baik dengan maupun tanpa ekstensi.");
                        }
                    }
                    // --- AKHIR VERIFIKASI ---

                } catch (\Exception $e) {
                     $errors[$photoType] = "Gagal memproses file '{$tempFilename}': " . $e->getMessage();
                     Log::error('Store (Fix Attempt) - Error: ' . $errors[$photoType]);
                     // Jangan hapus file temp jika gagal dipindah
                }
            } else {
                 $errors[$photoType] = "File sementara '{$tempFilename}' tidak ditemukan (Path: {$tempPathRelative}).";
                 Log::warning('Store (Fix Attempt) - Error File Temp: ' . $errors[$photoType]);
            }
        }

        // Jika ada error saat memproses foto, kembalikan error
        if (!empty($errors)) {
             // Hapus file yang mungkin sudah terlanjur dipindah di iterasi sebelumnya
             if (isset($finalPaths['foto_kwh'])) Storage::disk('public')->delete($finalPaths['foto_kwh']);
             if (isset($finalPaths['foto_bangunan'])) Storage::disk('public')->delete($finalPaths['foto_bangunan']);
             return response()->json(['errors' => $errors], 422);
        }

        // 4. Buat record di TemporaryMapping (HANYA JIKA SEMUA FOTO BERHASIL)
        unset($validatedData['foto_kwh'], $validatedData['foto_bangunan']); // Hapus nama file temp dari data DB
        $finalData = array_merge($validatedData, $finalPaths); // Gabung dengan path final (yang ada ekstensinya)
        TemporaryMapping::create($finalData);

        return response()->json(['message' => 'Data mapping berhasil ditambahkan!']);
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
            // 1. Cari data valid di tabel utama
            $validData = MappingKddk::findOrFail($id);
            $idpel = $validData->idpel;
            $objectid = $validData->objectid;

            // 2. Salin data ke array untuk tabel temporer
            $tempDataArray = $validData->toArray();

            // 3. Pindahkan foto dari 'verified' kembali ke 'unverified'
            $oldKwhPath = $validData->foto_kwh;
            $oldBangunanPath = $validData->foto_bangunan;
            $newKwhPath = null; // Inisialisasi
            $newBangunanPath = null; // Inisialisasi

            if ($oldKwhPath && Storage::disk('public')->exists($oldKwhPath)) {
                $newKwhPath = str_replace('verified', 'unverified', $oldKwhPath);
                Storage::disk('public')->move($oldKwhPath, $newKwhPath);
                $tempDataArray['foto_kwh'] = $newKwhPath; // Update path di array
            }
            
            if ($oldBangunanPath && Storage::disk('public')->exists($oldBangunanPath)) {
                $newBangunanPath = str_replace('verified', 'unverified', $oldBangunanPath);
                Storage::disk('public')->move($oldBangunanPath, $newBangunanPath);
                $tempDataArray['foto_bangunan'] = $newBangunanPath; // Update path di array
            }

            // 4. Hapus data yang tidak relevan & set status baru
            unset($tempDataArray['id'], $tempDataArray['created_at'], $tempDataArray['updated_at']);
            
            // --- Logika Status Baru ---
            // Kita set status baru sebagai "recalled" (ditarik)
            $tempDataArray['ket_validasi'] = 'recalled_1'; 
            $tempDataArray['validation_notes'] = $reason; // Simpan alasan penarikan
            $tempDataArray['validation_data'] = null;    // Pastikan kolom ini bersih
            $tempDataArray['locked_by'] = null;      // Pastikan tidak terkunci
            $tempDataArray['locked_at'] = null;

            // 5. Gunakan updateOrCreate untuk memasukkan data ke temporary_mappings
            // Ini aman jika data dengan IDPEL yg sama mungkin sudah ada
            TemporaryMapping::updateOrCreate(
                ['objectid' => $objectid], // Kunci pencarian
                $tempDataArray      // Data untuk di-create atau di-update
            );

            // 6. Hapus data dari mapping_kddk (tabel utama)
            $validData->delete();

            DB::commit();

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

            return back()->with('error', 'Gagal menarik data: ' . $e->getMessage());
        }
    }
}
