<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use App\Models\MappingKddk; 
use App\Models\MasterDataPelanggan;
use App\Models\HierarchyLevel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\ProcessMappingKddkImport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;

class MappingKddkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $hierarchyFilter = $this->getHierarchyFilterForJoin($user);
        $search = $request->input('search');
        $mappingStatus = null;
        $searchedIdpel = null;
        
        if ($search) {
            $searchedMapping = MappingKddk::query()
                ->select('idpel','mapping_kddk.ket_validasi')
                ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                    return $query->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                                 ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
                })

                ->where('mapping_kddk.idpel', 'like', "%{$search}%") 
                ->first();
            if ($searchedMapping) {
                $searchedIdpel = $searchedMapping->idpel;
                $mappingStatus = ($searchedMapping->ket_validasi === 'valid') ? 'valid' : 'unverified';
            }
        }
        // Menghitung data untuk kartu ringkasan
        $totalPelanggan = MasterDataPelanggan::count();
        $totalMappingEnabled = MappingKddk::where('mapping_kddk.enabled', true)
            ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                return $query->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                             ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
            })
            ->count();
        $mappingPercentage = ($totalPelanggan > 0) ? ($totalMappingEnabled / $totalPelanggan) * 100 : 0;
        
        // Mengambil input untuk pencarian dan sorting
        $search = $request->input('search');
        $sortColumn = $request->input('sort', 'mapping_kddk.created_at');
        $sortDirection = $request->input('direction', 'desc');

        // ==========================================================
        // == BLOK QUERY UTAMA YANG TELAH DIPERBAIKI ==
        // ==========================================================
        $query = MappingKddk::query()
            ->select('mapping_kddk.*')
            // 1. Terapkan filter hirarki
            ->when(!$user->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                return $query->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
                             ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
            })
            // 2. Terapkan filter pencarian
            ->when($search, function ($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('mapping_kddk.idpel', 'like', "%{$search}%")
                      ->orWhere('mapping_kddk.nokwhmeter', 'like', "%{$search}%")
                      ->orWhere('mapping_kddk.user_pendataan', 'like', "%{$search}%");
                });
            });
        
        // 3. Terapkan sorting
        $query->orderBy($sortColumn, $sortDirection);
        $mappings = $query->paginate(15)->withQueryString();
        
        // 4. Lakukan paginasi
        $mappings = $query->paginate(15)->withQueryString();

        // Siapkan semua data yang dibutuhkan oleh view
        $viewData = compact(
            'mappings', 'totalMappingEnabled', 'totalPelanggan', 'mappingPercentage',
            'search', 'sortColumn', 'sortDirection','mappingStatus','searchedIdpel'
        );

            // Logika untuk membedakan request biasa dan AJAX
        if ($request->has('is_ajax')) {
            return view('team.mapping-kddk.partials.index_content', $viewData);
            }
        return view('team.mapping-kddk.index',$viewData); 
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
            $allCoordinates = (clone $baseQuery)
                ->select('mapping_kddk.idpel', 'mapping_kddk.latitudey', 'mapping_kddk.longitudex')
                ->get();
            return response()->json(['all' => $allCoordinates]);
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

    /**
     * Show the form for creating a new resource.
     */

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
        
        $exampleObjectId = '12345';
        $exampleIdpel = '181405316052';

        $exampleRow = [
            'objectid' => $exampleObjectId,
            'idpel' => ' ' . $exampleIdpel,
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

    public function uploadForm()
    {
        return view('team.mapping-kddk.partials.upload_form');
    }

    public function uploadChunk(Request $request)
    {
        // Validasi permintaan untuk memastikan semua data chunk lengkap
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt', // Asumsi nama input file dari JS adalah 'file'
            'chunkIndex' => 'required|integer',
            'fileName' => 'required|string',
        ]);

        $validated['file']->storeAs(
            'temp_uploads/' . $validated['fileName'], 
            $validated['chunkIndex']
        );

       Log::info("Chunk {$validated['chunkIndex']} untuk {$validated['fileName']} berhasil diupload.");
       return response()->json(['message' => 'Chunk berhasil diupload.']);
    }

   public function mergeChunks(Request $request)
    {
        // Validasi ringkas
        $validated = $request->validate([
            'fileName' => 'required|string',
            'totalChunks' => 'required|integer',
            'totalSize' => 'required|integer',
        ]);

        $fileName = $validated['fileName'];
        $totalChunks = $validated['totalChunks'];
        $tempDir = 'temp_uploads/' . $fileName;
        $finalPath = 'imports/' . $fileName; // <-- BARIS INI TELAH DIPERBAIKI

        try {
            Storage::makeDirectory('imports'); // Pastikan direktori tujuan ada
            
            $finalFilePath = Storage::path($finalPath);
            $fileHandle = fopen($finalFilePath, 'w');

            // Asumsi chunk index dari JS dimulai dari 0
            for ($i = 0; $i < $totalChunks; $i++) { 
                $chunkPath = Storage::path($tempDir . '/' . $i);
                if (!file_exists($chunkPath)) {
                     throw new \Exception("Chunk {$i} hilang.");
                }
                fwrite($fileHandle, file_get_contents($chunkPath));
                unlink($chunkPath);
            }
            fclose($fileHandle);
            rmdir(Storage::path($tempDir));

            // Verifikasi ukuran file
            if (Storage::size($finalPath) != $validated['totalSize']) {
                Storage::delete($finalPath);
                throw new \Exception("Ukuran file tidak sesuai setelah digabungkan.");
            }

            // Mengirim path file dan ID user yang sedang login ke Job
            ProcessPelangganImport::dispatch($finalPath, auth()->id());

            Log::info("File {$fileName} berhasil digabungkan dan job dikirim oleh user ID: " . auth()->id());
            return response()->json(['message' => 'File berhasil di-upload dan sedang diproses di latar belakang.']);

        } catch (\Exception $e) {
            Log::error("Gagal menggabungkan chunks untuk {$fileName}: " . $e->getMessage());
            // Bersihkan file sisa jika terjadi error
            Storage::deleteDirectory($tempDir);
            Storage::delete($finalPath); // Hapus juga file final yang mungkin korup
            return response()->json(['error' => 'Gagal memproses file di server.'], 500);
        }
    }

    public function create()
    {
        return view('team.mapping-kddk.partials.create');
    }

    /**
     * Store a newly created resource in storage.
     */
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        // 2. Tambahkan data otomatis (user dan objectid baru)
        $validatedData['user_pendataan'] = Auth::user()->name;

        $lastObjectId = MappingKddk::max('objectid') ?? 0;
        $newObjectId = $lastObjectId + 1;
        $validatedData['objectid'] = $newObjectId;

        $idpel = $validatedData['idpel'];

        // Pindahkan file dari 'temp_photos' ke lokasi final 'unverified'
        foreach (['foto_kwh', 'foto_bangunan'] as $photoType) {
            $tempFilename = $validatedData[$photoType];
            $tempPath = 'temp_photos/' . $tempFilename;

            if (Storage::exists($tempPath)) {
                $newFilename = $newObjectId . '_' . $idpel . '_' . ($photoType === 'foto_kwh' ? 'kwh' : 'persil') . '.' . pathinfo($tempFilename, PATHINFO_EXTENSION);
                $finalRelativePath = "mapping_photos/unverified/{$idpel}/{$newFilename}";
                
                Storage::move($tempPath, 'public/' . $finalRelativePath);
                $validatedData[$photoType] = $finalRelativePath;
            } else {
                $validatedData[$photoType] = null;
            }
        }

        MappingKddk::create($validatedData);
        return response()->json(['message' => 'Data mapping berhasil ditambahkan!']);
    }

    public function uploadTemporaryPhoto(Request $request)
    {
        // Validasi file yang masuk
        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        // Simpan file di folder sementara dengan nama unik
        // 'temp_photos' akan berada di 'storage/app/temp_photos'
        $path = $request->file('photo')->store('temp_photos');

        // Kembalikan hanya nama file unik yang digenerate oleh Laravel
        return response()->json(['filename' => basename($path)]);
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

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
