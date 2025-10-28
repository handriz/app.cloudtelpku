<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use App\Models\TemporaryMapping;
use App\Models\MappingKddk;
use App\Models\HierarchyLevel;
use App\Jobs\ProcessMappingValidasiImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MappingValidationController extends Controller
{
    // Konstanta waktu timeout lock (dalam menit)
    const LOCK_TIMEOUT_MINUTES = 15;

    /**
     * Menampilkan halaman validasi.
     * Prioritaskan item yang sedang di-lock user, lalu ambil item acak lainnya.
     */
    public function index(Request $request)
    {
        $justRejectedId = $request->session()->pull('just_rejected_id', null);
        $userId = Auth::id();
        $lockExpirationTime = Carbon::now()->subMinutes(self::LOCK_TIMEOUT_MINUTES);

        // 1. Cari item yang di-lock user ini (belum expired)
        $userLockedItem = TemporaryMapping::where('locked_by', $userId)
                            ->where('locked_at', '>=', $lockExpirationTime)
                            ->first();

        // 2. Query dasar untuk item yang tersedia (unlocked atau expired)
        $hierarchyFilter = $this->getHierarchyFilterForJoin(Auth::user());
        $baseAvailableQuery = TemporaryMapping::where(function ($query) use ($lockExpirationTime) {
                $query->whereNull('locked_by')
                      ->orWhere('locked_at', '<', $lockExpirationTime);
                 })

                    ->when(!Auth::user()->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                        return $query->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                            ->select('temporary_mappings.*')
                            ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
                       
            });

        // 3. Ambil item acak lainnya (max 10), exclude item yg di-lock user ini
        $otherAvailableItems = (clone $baseAvailableQuery) // Clone query dasar
            ->when($userLockedItem, function ($query) use ($userLockedItem) {
                // Jangan ambil item yang sedang di-lock user ini dari daftar acak
                $query->where('temporary_mappings.id', '!=', $userLockedItem->id);
            })
            ->when($justRejectedId, function ($query) use ($justRejectedId) {
                $query->where('temporary_mappings.id', '!=', $justRejectedId);
            })
            ->inRandomOrder()
            ->take(6)
            ->get(); // Ambil sebagai collection

        // 4. Gabungkan: item yg di-lock user (jika ada) + item acak lainnya
        $availableItems = collect();
        if ($userLockedItem) {
            $availableItems->push($userLockedItem); // Tambahkan item user di awal
        }
        // Tambahkan item lain hingga maksimal 10
        $needed = 10 - $availableItems->count();
        if ($needed > 0) {
            $availableItems = $availableItems->merge($otherAvailableItems->take($needed));
        }

        // 5. Siapkan data untuk view
        // Hitung total yg BISA divalidasi (termasuk yg di-lock user ini jika ada)
        $totalAvailable = (clone $baseAvailableQuery)->count() + ($userLockedItem ? 1 : 0);

        // Item yg akan ditampilkan detailnya pertama kali (prioritas yg di-lock user)
        $currentItem = $userLockedItem ?: $availableItems->first();
        // Siapkan detailnya jika ada item yang akan ditampilkan
        $details = $currentItem ? $this->prepareItemDetails($currentItem) : null;

        $viewData = compact('availableItems', 'totalAvailable', 'currentItem', 'details');

        if ($request->has('is_ajax_list')) {
            // HANYA UNTUK REFRESH ANTRIAN
            return view('team.mapping-validation.partials.queue_list', compact('availableItems'));
        } elseif ($request->has('is_ajax')) {
            // UNTUK LOAD KONTEN TAB OLEH tab-manager.js
            return view('team.mapping-validation.partials.index_content', $viewData);
        } else {
            // UNTUK LOAD HALAMAN PENUH (BROWSER LANGSUNG)
            return view('team.mapping-validation.index', $viewData);
        }
    }

    /**
     * Mengunci item validasi (jika belum/expired/milik user) dan mengembalikan detailnya.
     */
    public function lockAndGetDetails(Request $request, $id)
    {
        $userId = Auth::id();
        $lockExpirationTime = Carbon::now()->subMinutes(self::LOCK_TIMEOUT_MINUTES);

        DB::beginTransaction();
        try {

            TemporaryMapping::where('locked_by', $userId)
            ->where('id', '!=', $id) // Kecuali item yang sedang dicoba dikunci ulang
            ->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);
            // Boleh lock jika: null ATAU expired ATAU milik user ini
            $item = TemporaryMapping::where('id', $id)
                ->where(function ($query) use ($lockExpirationTime, $userId) {
                    $query->whereNull('locked_by')
                          ->orWhere('locked_at', '<', $lockExpirationTime)
                          ->orWhere('locked_by', $userId); // <-- User boleh lock ulang miliknya
                })
                ->lockForUpdate() // Kunci baris DB sementara
                ->first();

            if (!$item) {
                // Item tidak ditemukan ATAU sudah valid di-lock user lain
                DB::rollBack();
                // Cek apakah benar di-lock user lain
                $lockedByAnother = TemporaryMapping::where('id', $id)
                                    ->where('locked_by', '!=', $userId) // Bukan user ini
                                    ->where('locked_at', '>=', $lockExpirationTime) // Lock masih valid
                                    ->exists();
                $message = $lockedByAnother ? 'Item ini sedang divalidasi oleh user lain.' : 'Item tidak ditemukan atau sudah diproses.';
                return response()->json(['error' => $message], 409); // 409 Conflict
            }

            // Update status lock (perbarui waktu jika sudah di-lock user ini)
            $item->locked_by = $userId;
            $item->locked_at = Carbon::now();
            $item->save();

            DB::commit(); // Simpan perubahan lock

            // Siapkan detail untuk dikirim ke JS
            $details = $this->prepareItemDetails($item);
            return response()->json([
                'currentItemId' => $item->id, // Kirim ID item yg berhasil di-lock
                'details' => $details,
            ]);

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan jika ada error
            \Log::error("Gagal lock item validasi ID {$id} oleh user {$userId}: " . $e->getMessage()); // Catat error
            return response()->json(['error' => 'Gagal mengunci item. Silakan coba lagi.'], 500); // Error server
        }
    }

    private function prepareItemDetails(TemporaryMapping $item): array
    {
        $fotoKwhUrl = $item->foto_kwh ? Storage::disk('public')->url($item->foto_kwh) : null;
        $fotoBangunanUrl = $item->foto_bangunan ? Storage::disk('public')->url($item->foto_bangunan) : null;
        $lat = (float) $item->latitudey;
        $lon = (float) $item->longitudex;

        $rejectionHistory = [];
        // Cek apakah item ini pernah ditolak
        if ($item->ket_validasi && (str_starts_with($item->ket_validasi, 'rejected_') || str_starts_with($item->ket_validasi, 'recalled_'))) {
            
            // --- PERBAIKAN DIMULAI DI SINI ---
            
            // 1. Ambil data mentah (yang saat ini berupa string)
            $rawValidationData = $item->validation_data;
            $decodedData = null; // Siapkan variabel untuk array

            // 2. Cek apakah datanya string, lalu decode
            if (is_string($rawValidationData)) {
                // json_decode($string, true) mengubah string JSON menjadi array PHP
                $decodedData = json_decode($rawValidationData, true);
            } 
            // Jika datanya sudah array (karena cast model akhirnya bekerja), pakai saja
            elseif (is_array($rawValidationData)) {
                $decodedData = $rawValidationData;
            }

            // 3. Kirim data yang SUDAH PASTI ARRAY (atau null) ke fungsi
            $rejectionHistory = $this->formatRejectionHistory(
                $decodedData, // <-- Gunakan data yang sudah di-decode
                $item->validation_notes
            );
        }
        return [
            'idpel'             => $item->idpel,
            'user_pendataan'    => $item->user_pendataan,
            'keterangan'        => $item->ket_survey,
            'lat'               => $lat,
            'lon'               => $lon,
            'foto_kwh_url'      => $fotoKwhUrl,
            'foto_bangunan_url' => $fotoBangunanUrl,
            'full_meter_number' => $item->nokwhmeter ?? null, // Kunci jawaban
            'status_validasi'   => $item->ket_validasi,
            'rejection_history' => $rejectionHistory
        ];
    }

    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Inisialisasi variabel untuk jangkauan (scope) catch block
            $objectid = null;
            $oldKwhPath = null;
            $oldBangunanPath = null;
            $newPathKwh = null;
            $newPathBangunan = null;

            // Pastikan user ini yang mengunci item
            $tempData = TemporaryMapping::where('id', $id)
                        ->where('locked_by', Auth::id())
                        ->lockForUpdate()
                        ->firstOrFail(); // Gagal jika ID salah atau di-lock user lain

            $idpel = $tempData->idpel;
            $objectid = $tempData->objectid;

            // 1. Salin data ke array & atur status valid
            $validatedData = $tempData->toArray();
            $validatedData['ket_validasi'] = 'valid';

            // 2. Pindahkan foto dari 'unverified' ke 'verified'
            $newPhotoPaths = [];
            $oldKwhPath = $tempData->foto_kwh;
            $oldBangunanPath = $tempData->foto_bangunan;
            
            // Pindahkan foto KWH
            if ($oldKwhPath && Storage::disk('public')->exists($oldKwhPath)) {
                $newPathKwh = str_replace('unverified', 'verified', $oldKwhPath); // <-- Pastikan $oldKwhPath
                Storage::disk('public')->move($oldKwhPath, $newPathKwh);
                $newPhotoPaths['foto_kwh'] = $newPathKwh;
            }
            // Pindahkan foto Bangunan
            if ($oldBangunanPath && Storage::disk('public')->exists($oldBangunanPath)) {
                $newPathBangunan = str_replace('unverified', 'verified', $oldBangunanPath); // <-- Pastikan $oldBangunanPath
                Storage::disk('public')->move($oldBangunanPath, $newPathBangunan);
                $newPhotoPaths['foto_bangunan'] = $newPathBangunan;
            }

            // 3. Buat entri baru di tabel mapping_kddk utama
            unset(
                $validatedData['id'], 
                $validatedData['locked_by'], 
                $validatedData['locked_at'],
                $validatedData['validation_data'],
                $validatedData['validation_notes'],
                $validatedData['foto_kwh'],      // <-- Perbaikan
                $validatedData['foto_bangunan']  // <-- Perbaikan
            );
            MappingKddk::updateOrCreate(
                ['objectid' => $objectid], // Kunci pencarian
                array_merge($validatedData, $newPhotoPaths) // Data baru/update
            );

            // 4. Hapus entri dari tabel temporary
            $tempData->delete();

            DB::commit();

            // 5. Kirim respon sukses (untuk AJAX)
            if ($request->expectsJson()) {
                 // Cari item berikutnya yang tersedia untuk divalidasi user ini
                 $nextItem = $this->findNextAvailableItem(Auth::id());

                 if ($nextItem) {
                    // 1. Panggil fungsi lockAndGetDetails, TAPI simpan responsenya
                    $response = $this->lockAndGetDetails($request, $nextItem->id);
                    // 2. Ambil data (sebagai array) dari JsonResponse itu
                    $data = $response->getData(true);
                    // 3. Tambahkan key 'status_message' yang Anda inginkan
                    $data['status_message'] = 'Data Mapping Idpel ' . $idpel . ' (Object ID: ' . $objectid . ') berhasil divalidasi. Item berikutnya telah dimuat.';
                    // 4. (Opsional tapi disarankan) Tambahkan juga action_type
                    $data['action_type'] = 'validate';
                    // 5. Kembalikan data yang sudah dimodifikasi
                    return response()->json($data);
                } else {
                    // Jika antrian habis
                    return response()->json([
                        'action_type' => 'validate',
                        'status_message' => 'Data' . $idpel . ' berhasil divalidasi. Tidak ada data lagi untuk divalidasi saat ini.',
                        'queue_empty' => true
                        ]);
                    }
            }
            // Jika bukan AJAX, redirect biasa
            return redirect()->route('team.mapping.validation.index')->with('success', 'Data IDPEL ' . $idpel . ' (Object ID: ' . $objectid . ') berhasil divalidasi.');

            } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika error

            // Kembalikan foto yang mungkin terlanjur dipindah (rollback file)
            if (isset($newPathKwh) && Storage::disk('public')->exists($newPathKwh)) {
                Storage::disk('public')->move($newPathKwh, $oldKwhPath); // Kembalikan ke unverified
            }
            if (isset($newBangunanPath) && Storage::disk('public')->exists($newBangunanPath)) {
                 Storage::disk('public')->move($newBangunanPath, $oldBangunanPath); // Kembalikan ke unverified
            }

            $logObjectId = $objectid ?? 'N/A';
            \Log::error("Gagal validasi item ID {$id} (Object ID: {$logObjectId}): " . $e->getMessage());
            // Kirim error JSON jika AJAX
             if ($request->expectsJson()) {
                 return response()->json(['error' => 'Gagal memvalidasi data: ' . $e->getMessage()], 500);
             }
             // Jika bukan AJAX, redirect biasa
             return redirect()->route('team.mapping.validation.index')->with('error', 'Gagal memvalidasi data: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Pastikan user ini yang mengunci item
            $tempData = TemporaryMapping::where('id', $id)
                        ->where('locked_by', Auth::id())
                        ->lockForUpdate()
                        ->firstOrFail();

            $idpel = $tempData->idpel;

            // Logika Increment Reject Counter
            $currentStatus = $tempData->ket_validasi;
            $rejectCount = 0;
            
            if ($currentStatus && preg_match('/^rejected_(\d+)/', $currentStatus, $matches)) {
                // Format baru: 'rejected_1', 'rejected_2'
                $rejectCount = (int) $matches[1];
            } elseif ($currentStatus && preg_match('/^rejected sebanyak (\d+) kali/', $currentStatus, $matches)) {
                // Format lama yang salah: 'rejected sebanyak 1 kali'
                $rejectCount = (int) $matches[1];
            }
            
            $newStatus = 'Rejected sebanyak' . ($rejectCount + 1) . 'kali';

            // Update status, simpan alasan (jika ada), dan reset lock

            $validationData = $request->input('validation_data');
            $validationNotes = $request->input('validation_notes');

            \Log::info('Validation Data Received:', ['data' => $validationData, 'notes' => $validationNotes]);
            $tempData->ket_validasi = $newStatus;
            $tempData->validation_data = $validationData;
            $tempData->validation_notes = $validationNotes;
            $tempData->locked_by = null; // Lepas lock
            $tempData->locked_at = null;
            $tempData->save();

            DB::commit();

            // Simpan ID ini ke session agar tidak langsung muncul lagi di antrian
            $request->session()->put('just_rejected_id', $tempData->id);

            // Kirim respon sukses (untuk AJAX)
            if ($request->expectsJson()) {
                 // Cari item berikutnya yang tersedia
                 $nextItem = $this->findNextAvailableItem(Auth::id());
                 if ($nextItem) {
                    $response = $this->lockAndGetDetails($request, $nextItem->id);
                    $data = $response->getData(true);
                    $data['validation_status'] = 'reject';
                    $data['status_message'] = 'Data Mapping Idpel ' . $idpel . ' berhasil ditolak. Item berikutnya telah dimuat.';
                    return response()->json($data);

                } else {
                    return response()->json([
                    'validation_status' => 'reject', // Tambahkan status ini
                    'status_message' => 'Data berhasil ditolak. Untuk saat ini tidak ada data lagi untuk divalidasi.',
                    'queue_empty' => true
                 ]);
                }
            }
            return redirect()->route('team.mapping-validation.index')->with('success', 'Data IDPEL ' . $idpel . ' berhasil ditolak.');
        } catch (\Exception $e) {
            DB::rollBack();
             if ($request->expectsJson()) {
                 return response()->json(['error' => 'Gagal menolak data: ' . $e->getMessage()], 500);
             }
            return redirect()->route('team.mapping-validation.index')->with('error', 'Gagal menolak data: ' . $e->getMessage());
        }
    }

    private function findNextAvailableItem($userId)
    {
         $lockExpirationTime = Carbon::now()->subMinutes(self::LOCK_TIMEOUT_MINUTES);
         $hierarchyFilter = $this->getHierarchyFilterForJoin(Auth::user());
         
         // Cari item acak yang tersedia (bukan yg sedang di-lock user lain)
         $query= TemporaryMapping::where(function ($query) use ($lockExpirationTime, $userId) {
                $query->whereNull('locked_by')
                      ->orWhere('locked_at', '<', $lockExpirationTime);
            })

            ->when(!Auth::user()->hasRole('admin'), function ($query) use ($hierarchyFilter) {
                return $query->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                    ->select('temporary_mappings.*')
                    ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
             });

             return $query->inRandomOrder()->first();
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

    private function formatRejectionHistory(?array $data, ?string $notes): array
    {
        if (empty($data) && empty($notes)) {
            return [];
        }

        $history = [];

        // Definisikan terjemahan untuk kode alasan
        $petaReasons = [
            'posisi_bangunan' => 'Posisi titik tagging tidak berada di bangunan',
            'luar_wilayah' => 'Titik koordinat tidak valid atau berada diluar wilayah ULP / UP3',
        ];
        $persilReasons = [
            'bukan_persil' => 'Bukan Foto Persil / Bangunan',
            'diragukan' => 'Foto App Tidak Ada',
            'tidak_valid' => 'Foto Diragukan dari kegiatan lapangan',
        ];

        // 1. Cek Peta
        if (isset($data['eval_peta']) && $data['eval_peta'] === 'tidak') {
            $history[] = [
                'label' => 'Evaluasi Peta',
                'value' => 'Tidak Sesuai'
            ];
            if (!empty($data['eval_peta_reason'])) {
                $history[] = [
                    'label' => 'Alasan Peta',
                    'value' => $petaReasons[$data['eval_peta_reason']] ?? $data['eval_peta_reason']
                ];
            }
        }

        // 2. Cek Persil
        if (isset($data['eval_persil']) && $data['eval_persil'] === 'tidak') {
            $history[] = [
                'label' => 'Evaluasi Persil',
                'value' => 'Tidak Sesuai'
            ];
            if (!empty($data['eval_persil_reason'])) {
                $history[] = [
                    'label' => 'Alasan Persil',
                    'value' => $persilReasons[$data['eval_persil_reason']] ?? $data['eval_persil_reason']
                ];
            }
        }

        // 3. Catat input meter terakhir (jika ada)
        if (!empty($data['eval_meter_input'])) {
            $history[] = [
                'label' => 'Input No. Meter (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_meter_input']) // Keamanan
            ];
        }

        // 4. Tambahkan catatan bebas
        if (!empty($notes)) {
            $history[] = [
                'label' => 'Catatan Tambahan Validator',
                'value' => htmlspecialchars($notes) // Keamanan
            ];
        }

        return $history;
    }

    public function uploadForm()
    {
        return view('team.mapping-validation.partials.upload_form');
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
            ProcessMappingValidasiImport::dispatch($finalPath, auth()->id());

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

    public function uploadPhotosForm() // <-- METHOD BARU
    {
        // Cukup tampilkan view partial yang sudah kita buat
        return view('team.mapping-validation.partials.upload_photos_form');
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        // 2. Tambahkan data otomatis (user dan objectid baru)
        $validatedData['user_pendataan'] = Auth::user()->name;

        $lastTempId = TemporaryMapping::max('objectid');
        $lastKddkId = MappingKddk::max('objectid');

        $trueLastObjectId = max($lastTempId, $lastKddkId);
        $newObjectId = ($trueLastObjectId !== null ? $trueLastObjectId : 0) + 1;

        $validatedData['objectid'] = $newObjectId;
        $idpel = $validatedData['idpel'];

        $finalPaths = [];

        // 3. Pindahkan file dari disk 'local' (temp) ke disk 'public' (unverified)
        foreach (['foto_kwh', 'foto_bangunan'] as $photoType) {
            $tempFilename = $validatedData[$photoType];
            $tempPath = 'temp_photos/' . $tempFilename;

            // 1. Cek apakah file sementara ada di disk 'local'
            if (Storage::exists($tempPath)) { 

                $extension = pathinfo($tempFilename, PATHINFO_EXTENSION);
                // 2. Tentukan nama dan path file baru di disk 'public'
                if (empty($extension)) {
                    if (isset($finalPaths['foto_kwh'])) {
                        Storage::disk('public')->delete($finalPaths['foto_kwh']);
                    }
                    $errorMsg = "File sementara untuk $photoType tidak memiliki ekstensi. Harap upload ulang.";
                    Log::warning($errorMsg . " (Temp path: $tempPath, User: " . Auth::id() . ")");
                    return response()->json(['errors' => [$photoType => [$errorMsg]]], 422);
                }

                $newFilename = $newObjectId . '_' . $idpel . '_' . ($photoType === 'foto_kwh' ? 'foto_app' : 'foto_persil') . '.' . pathinfo($tempFilename, PATHINFO_EXTENSION);
                $finalRelativePath = "mapping_photos/unverified/{$idpel}/{$newFilename}";

                // 3. Baca file dari 'local' dan tulis ke 'public'
                Storage::disk('public')->put($finalRelativePath, Storage::disk('local')->get($tempPath));
                
                // 4. Hapus file asli dari 'local' setelah berhasil disalin
                Storage::disk('local')->delete($tempPath);

                // 5. Simpan path BARU (relatif terhadap disk 'public') ke database
                $finalPaths[$photoType] = $finalRelativePath;
            } else {
                // File sementara tidak ditemukan di disk 'local'
                if (isset($finalPaths['foto_kwh'])) {
                    Storage::disk('public')->delete($finalPaths['foto_kwh']);
                }

                $errorField = $photoType;
                $errorMsg = "File sementara untuk $photoType tidak ditemukan di server. Harap upload ulang foto.";
                Log::warning($errorMsg . " (Temp path: $tempPath, User: " . Auth::id() . ")");
                return response()->json(['errors' => [$errorField => [$errorMsg]]], 422);
            }
        }

        $finalData = array_merge($validatedData, $finalPaths);
        TemporaryMapping::create($finalData);

        return response()->json(['message' => 'Data mapping berhasil ditambahkan!']);
    }
    
    public function uploadBatchPhotos(Request $request)
    {
        $request->validate([
            'photos.*' => 'required|image|mimes:jpg,jpeg,png|max:2048' // Validasi setiap file foto
        ]);

        $uploadedCount = 0;
        $failedCount = 0;
        $errors = [];
        $inboxPath = 'temp_photo_uploads'; // Folder inbox sementara

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                try {
                    // Simpan ke storage/app/temp_photo_uploads/ dengan nama asli
                    $photo->storeAs($inboxPath, $photo->getClientOriginalName());
                    $uploadedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "Gagal menyimpan {$photo->getClientOriginalName()}: " . $e->getMessage();
                    Log::error("Gagal simpan foto batch: {$photo->getClientOriginalName()} - " . $e->getMessage());
                }
            }
        }

        if ($failedCount > 0) {
            return response()->json([
                'message' => "{$uploadedCount} foto berhasil diunggah ke inbox, {$failedCount} gagal.",
                'errors' => $errors
            ], 422); // Kirim status error jika ada kegagalan
        }

        return response()->json(['message' => "{$uploadedCount} foto berhasil diunggah ke inbox server."]);
    }
} 