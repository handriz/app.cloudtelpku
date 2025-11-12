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
    const LOCK_TIMEOUT_MINUTES = 10;

    /**
     * Menampilkan halaman validasi.
     * Prioritaskan item yang sedang di-lock user, lalu ambil item acak lainnya.
     */
    /**
     * Menampilkan halaman validasi.
     * Prioritaskan item yang sedang di-lock user, lalu ambil item acak lainnya.
     *
     * VERSI OPTIMASI: 
     * - Menghilangkan inRandomOrder()
     * - Menerapkan antrian 2-Tier (Fresh > Rejected)
     * - Secara eksplisit membaca status 'pending'
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

        // 2. Query dasar untuk SEMUA item yang BISA Divalidasi (belum 'verified')
        $hierarchyFilter = $this->getHierarchyFilterForJoin(Auth::user());
        $baseAvailableQuery = TemporaryMapping::where(function ($query) use ($lockExpirationTime) {
            
            // Filter 1: Kriteria Lock (Tidak terkunci ATAU expired)
            $query->where(function($q) use ($lockExpirationTime) {
                $q->whereNull('locked_by')
                  ->orWhere('locked_at', '<', $lockExpirationTime);
            });
            
            // Filter 2: Kriteria Status (BUKAN 'verified')
            $query->where(function($q) {
                $q->whereNull('ket_validasi') // Item baru (NULL)
                  ->orWhere('ket_validasi', 'NOT LIKE', 'verified%'); // Item yg belum di-approve
            });

        })
        ->when(!Auth::user()->hasRole('admin'), function ($query) use ($hierarchyFilter) {
            return $query->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                ->select('temporary_mappings.*')
                ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
        });

        // 3. Ambil item acak lainnya (max 10), prioritaskan item BARU
        
        // --- TIER 1: Ambil item BARU (Fresh Items) ---
        $freshItemsQuery = (clone $baseAvailableQuery)
            ->when($userLockedItem, function ($query) use ($userLockedItem) {
                $query->where('temporary_mappings.id', '!=', $userLockedItem->id);
            })
            ->when($justRejectedId, function ($query) use ($justRejectedId) {
                $query->where('temporary_mappings.id', '!=', $justRejectedId);
            })
            // --- INI PERBAIKANNYA ---
            // Secara eksplisit cari status: NULL, pending, atau recalled
            ->where(function ($query) {
                $query->whereNull('temporary_mappings.ket_validasi')
                      ->orWhere('temporary_mappings.ket_validasi', 'pending')
                      ->orWhere('temporary_mappings.ket_validasi', 'LIKE', 'recalled_%');
            });

        // Ambil 6 item baru, acak dari 200 tertua
        $otherAvailableItems = (clone $freshItemsQuery)
            ->orderBy('temporary_mappings.created_at', 'asc')
            ->take(200)
            ->get()
            ->shuffle()
            ->take(6); // Ambil 6 item

        // Cek berapa slot lagi yang perlu diisi
        $neededMore = 10 - ($userLockedItem ? 1 : 0) - $otherAvailableItems->count();
        $neededMore = max(0, $neededMore); // Pastikan tidak negatif

        // --- TIER 2: Jika slot masih ada, ambil item REJECTED ---
        if ($neededMore > 0) {
            $rejectedItemsQuery = (clone $baseAvailableQuery)
                ->when($userLockedItem, function ($query) use ($userLockedItem) {
                    $query->where('temporary_mappings.id', '!=', $userLockedItem->id);
                })
                ->when($justRejectedId, function ($query) use ($justRejectedId) {
                    $query->where('temporary_mappings.id', '!=', $justRejectedId);
                })
                // Filter hanya item yang pernah di-reject
                ->where('temporary_mappings.ket_validasi', 'LIKE', 'rejected%');
            
            $rejectedItems = (clone $rejectedItemsQuery)
                ->orderBy('temporary_mappings.created_at', 'asc') // Ambil item rejected tertua
                ->take(200)
                ->get()
                ->shuffle()
                ->take($neededMore); // Ambil sisanya

            // Gabungkan item baru dengan item rejected
            $otherAvailableItems = $otherAvailableItems->merge($rejectedItems);
        }

        // 4. Gabungkan: item yg di-lock user (jika ada) + item acak lainnya
        $availableItems = collect();
        if ($userLockedItem) {
            $availableItems->push($userLockedItem); // Tambahkan item user di awal
        }
        $availableItems = $availableItems->merge($otherAvailableItems);

        // 5. Siapkan data untuk view
        $totalAvailable = (clone $baseAvailableQuery)->count() + ($userLockedItem ? 1 : 0);
        $currentItem = $userLockedItem ?: $availableItems->first();
        $details = $currentItem ? $this->prepareItemDetails($currentItem) : null;
        $viewData = compact('availableItems', 'totalAvailable', 'currentItem', 'details');

        if ($request->has('is_ajax_list')) {
            return view('team.mapping-validation.partials.queue_list', compact('availableItems'));
        } elseif ($request->has('is_ajax')) {
            return view('team.mapping-validation.partials.index_content', $viewData);
        } else {
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
            'rejection_history' => $rejectionHistory,
            'mcb'               => $item->mcb,
            'type_pbts'         => $item->type_pbts,
            'merkkwhmeter'      => $item->merkkwhmeter,
            'tahun_buat'        => $item->tahun_buat,
            'sr'                => $item->sr,
            'latitudey_sr'      => $item->latitudey_sr,
            'longitudex_sr'     => $item->longitudex_sr,
            'enabled'           => $item->enabled,
        ];
    }

    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // Pastikan user ini yang mengunci item
            $tempData = TemporaryMapping::where('id', $id)
                        ->where('locked_by', Auth::id())
                        ->lockForUpdate()
                        ->firstOrFail(); // Gagal jika ID salah atau di-lock user lain

            // 1. Update SEMUA input evaluasi di tabel temporary
            $tempData->mcb = $request->input('eval_mcb');
            $tempData->type_pbts = $request->input('eval_type_pbts');
            $tempData->merkkwhmeter = $request->input('eval_merkkwhmeter');
            $tempData->tahun_buat = $request->input('eval_tahun_buat');
            $tempData->sr = $request->input('eval_sr');
            $tempData->latitudey_sr = $request->input('eval_latitudey_sr');
            $tempData->longitudex_sr = $request->input('eval_longitudex_sr');

            // 2. Tandai item SELESAI validasi (is_validated = true)
            $tempData->is_validated = true;
            $tempData->ket_validasi = 'validated';
            $tempData->user_validasi = Auth::id();

            // 3. Simpan data evaluasi
            $evalData = $request->input('validation_data');
            $tempData->validation_data = $evalData;
            $tempData->validation_notes = $request->input('validation_notes');

            // 4. Lepaskan lock
            $tempData->locked_by = null;
            $tempData->locked_at = null;
            $tempData->save();
            DB::commit();

            // 5. Siapkan respon
            $idpel = $tempData->idpel;

            // $objectid = null;
            // $oldKwhPath = null;
            // $oldBangunanPath = null;
            // $newPathKwh = null;
            // $newPathBangunan = null;



            // $idpel = $tempData->idpel;
            // $objectid = $tempData->objectid;

            // // 1. Salin data ke array & atur status valid
            // $validatedData = $tempData->toArray();

            // $validatedData['ket_validasi'] = 'verified';
            // $validatedData['enabled'] = false;
            // $validatedData['user_validasi']    = Auth::user()->id;

            // // 2. Pindahkan foto dari 'unverified' ke 'verified'
            // $newPhotoPaths = [];
            // $oldKwhPath = $tempData->foto_kwh;
            // $oldBangunanPath = $tempData->foto_bangunan;
            
            // // Pindahkan foto KWH
            // if ($oldKwhPath && Storage::disk('public')->exists($oldKwhPath)) {
            //     $newPathKwh = str_replace('unverified', 'verified', $oldKwhPath); // <-- Pastikan $oldKwhPath
            //     Storage::disk('public')->move($oldKwhPath, $newPathKwh);
            //     $newPhotoPaths['foto_kwh'] = $newPathKwh;
            // }
            // // Pindahkan foto Bangunan
            // if ($oldBangunanPath && Storage::disk('public')->exists($oldBangunanPath)) {
            //     $newPathBangunan = str_replace('unverified', 'verified', $oldBangunanPath); // <-- Pastikan $oldBangunanPath
            //     Storage::disk('public')->move($oldBangunanPath, $newPathBangunan);
            //     $newPhotoPaths['foto_bangunan'] = $newPathBangunan;
            // }

            // // 3. Bersihkan data array sebelum pindah ke tabel utama
            // unset(
            //     $validatedData['id'], 
            //     $validatedData['locked_by'], 
            //     $validatedData['locked_at'],
            //     $validatedData['validation_data'],
            //     $validatedData['validation_notes'],
            //     $validatedData['foto_kwh'],     
            //     $validatedData['foto_bangunan']  
            // );

            // // 5. Buat entri baru di tabel mapping_kddk utama
            // MappingKddk::updateOrCreate(
            //     ['objectid' => $objectid], // Kunci pencarian
            //     array_merge($validatedData, $newPhotoPaths) // Data baru/update
            // );

            // // 6. Hapus entri dari tabel temporary
            // $tempData->delete();

            // DB::commit();

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
                        'status_message' => 'Idpel ' . $idpel . ' berhasil divalidasi. Tidak ada data lagi untuk divalidasi saat ini.',
                        'queue_empty' => true
                        ]);
                    }
            }
            // Jika bukan AJAX, redirect biasa
            return redirect()->route('team.mapping.validation.index')->with('success', 'Data IDPEL ' . $idpel . ' (Object ID: ' . $objectid . ') berhasil divalidasi.');

            } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika error
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
            } elseif ($currentStatus && preg_match('/^Rejected sebanyak (\d+) kali/', $currentStatus, $matches)) {
                // Format lama yang salah: 'Rejected sebanyak 1 kali'
                $rejectCount = (int) $matches[1];
            }
            
            // --- PERBAIKAN BUG DI SINI ---
            // Gunakan format yang konsisten (rejected_X)
            $newStatus = 'rejected_' . ($rejectCount + 1);

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
                    
                    // --- PERBAIKAN BUG DI SINI ---
                    // Ganti 'validation_status' menjadi 'action_type' agar konsisten
                    $data['action_type'] = 'reject'; 
                    $data['status_message'] = 'Data Mapping Idpel ' . $idpel . ' berhasil ditolak. Item berikutnya telah dimuat.';
                    return response()->json($data);

                } else {
                    return response()->json([
                    'action_type' => 'reject', // Konsisten
                    'status_message' => 'Data berhasil ditolak. Untuk saat ini tidak ada data lagi untuk divalidasi.',
                    'queue_empty' => true
                 ]);
                }
            }
            return redirect()->route('team.mapping_validation.index')->with('success', 'Data IDPEL ' . $idpel . ' berhasil ditolak.');
        
        } catch (\Exception $e) {
            DB::rollBack();
             if ($request->expectsJson()) {
                 return response()->json(['error' => 'Gagal menolak data: ' . $e->getMessage()], 500);
             }
            return redirect()->route('team.mapping_validation.index')->with('error', 'Gagal menolak data: ' . $e->getMessage());
        }
    }

    private function findNextAvailableItem($userId)
    {
         $lockExpirationTime = Carbon::now()->subMinutes(self::LOCK_TIMEOUT_MINUTES);
         $hierarchyFilter = $this->getHierarchyFilterForJoin(Auth::user());
         
         // Query dasar untuk item yang tersedia (bukan 'verified')
         $query = TemporaryMapping::where(function ($query) use ($lockExpirationTime) {
            
            // Filter 1: Kriteria Lock
            $query->where(function($q) use ($lockExpirationTime) {
                $q->whereNull('locked_by')
                  ->orWhere('locked_at', '<', $lockExpirationTime);
            });
            
            // Filter 2: Kriteria Status
            $query->where(function($q) {
                $q->whereNull('ket_validasi')
                  ->orWhere('ket_validasi', 'NOT LIKE', 'verified%');
            });
         })
         ->when(!Auth::user()->hasRole('admin'), function ($query) use ($hierarchyFilter) {
             return $query->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                 ->select('temporary_mappings.*')
                 ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
          });

         // --- TIER 1: Prioritaskan item BARU (Fresh) ---
         $freshItem = (clone $query)
            // --- INI PERBAIKANNYA ---
            ->where(function ($q) {
                $q->whereNull('temporary_mappings.ket_validasi')
                  ->orWhere('temporary_mappings.ket_validasi', 'pending')
                  ->orWhere('temporary_mappings.ket_validasi', 'LIKE', 'recalled_%');
            })
            ->orderBy('temporary_mappings.created_at', 'asc')
            ->first();

        if ($freshItem) {
            return $freshItem; // Kembalikan item baru jika ada
        }

        // --- TIER 2: Jika item baru habis, baru ambil item REJECTED ---
        return (clone $query)
            ->where('temporary_mappings.ket_validasi', 'LIKE', 'rejected%')
            ->orderBy('temporary_mappings.created_at', 'asc') // Ambil item rejected tertua
            ->first();
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
            'luar_wilayah' => 'Titik tagging berada diluar wilayah ULP / UP3 Pekanbaru',
        ];
        $persilReasons = [
            'bukan_persil' => 'Bukan foto persil / bangunan',
            'diragukan' => 'Foto App tidak ada pada persil',
            'tidak_valid' => 'Foto diragukan dari kegiatan lapangan',
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

        if (!empty($data['eval_mcb'])) {
            $history[] = [
                'label' => 'Input MCB (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_mcb'])
            ];
        }

        if (!empty($data['eval_type_pbts'])) {
            $history[] = [
                'label' => 'Input Tipe PB/TS (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_type_pbts'])
            ];
        }

        if (!empty($data['eval_merkkwhmeter'])) {
            $history[] = [
                'label' => 'Input Merk KWH (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_merkkwhmeter'])
            ];
        }

        if (!empty($data['eval_tahun_buat'])) {
            $history[] = [
                'label' => 'Input Tahun Buat (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_tahun_buat'])
            ];
        }
        if (!empty($data['eval_sr'])) {
            $history[] = [
                'label' => 'Input Tipe SR (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_sr'])
            ];
        }
        if (!empty($data['eval_latitudey_sr'])) {
            $history[] = [
                'label' => 'Input Lat SR (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_latitudey_sr'])
            ];
        }
        if (!empty($data['eval_longitudex_sr'])) {
            $history[] = [
                'label' => 'Input Lon SR (Saat Ditolak)',
                'value' => htmlspecialchars($data['eval_longitudex_sr'])
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

        $tempDir = 'temp_uploads/' . $validated['fileName'];
        Storage::makeDirectory($tempDir);
        
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
            'idpel'         => 'required|numeric|regex:/^[0-9]+$/',
            'foto_kwh'      => 'required|string',
            'foto_bangunan' => 'required|string',
            'ket_survey'    => 'required|string',
            //Perkiraan Batas Koordinat Provinsi Riau:
            'latitudey'  => ['required', 'numeric', 'between:-1.5,1.8'],
            'longitudex' => ['required', 'numeric', 'between:100.0,104.5'],
            ], [
            // Pesan error kustom yang lebih ramah pengguna
            'latitudey.numeric' => 'Latitude harus berupa angka.',
            'latitudey.between' => 'Nilai Latitude harus di antara -90 dan 90.',
            'longitudex.numeric' => 'Longitude harus berupa angka.',
            'longitudex.between' => 'Nilai Longitude harus di antara -180 dan 180.',                  
        ]);

        if ($validator->fails()) {
            if ($request->filled('foto_kwh')) Storage::disk('local')->delete('temp_photos/' . basename($request->input('foto_kwh')));
            if ($request->filled('foto_bangunan')) Storage::disk('local')->delete('temp_photos/' . basename($request->input('foto_bangunan')));
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $validatedData['user_pendataan'] = Auth::user()->name;
        $idpel = $validatedData['idpel'];

        $finalPaths = [];
        $tempFilesToDelete = [];
        $newObjectId = null; // Inisialisasi

        // Mulai Transaksi DB
        DB::beginTransaction();
        try {
            
            // --- INI LOGIKA BARU YANG LEBIH KUAT ---
            // 2. Minta ID unik baru dari database
            // Kita membuat entri dummy di tabel sequence...
            DB::table('objectid_sequence')->insert(['created_at' => now()]);
            // ...dan langsung ambil ID auto-increment yang baru saja dibuat
            $newObjectId = DB::getPdo()->lastInsertId();
            // $newObjectId sekarang DIJAMIN unik
            // --- AKHIR LOGIKA BARU ---

            $validatedData['objectid'] = $newObjectId;

            // 3. Pindahkan file (logika ini tetap sama)
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
            
            // 4. Buat record di TemporaryMapping
            unset($validatedData['foto_kwh'], $validatedData['foto_bangunan']);
            $finalData = array_merge($validatedData, $finalPaths);
            TemporaryMapping::create($finalData);

            // 5. Commit DB
            DB::commit();

            // 6. Hapus file temp HANYA JIKA commit berhasil
            foreach($tempFilesToDelete as $tempPath) {
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
            }
            
            return response()->json(['message' => 'Data mapping berhasil ditambahkan!']);

        } catch (\Exception $e) {
            // 7. Rollback jika gagal
            DB::rollBack();

            // Rollback pemindahan file
            if (isset($finalPaths['foto_kwh'])) Storage::disk('public')->delete($finalPaths['foto_kwh']);
            if (isset($finalPaths['foto_bangunan'])) Storage::disk('public')->delete($finalPaths['foto_bangunan']);

            Log::error("Gagal store mapping KDDK (Controller): " . $e->getMessage());
            return response()->json(['errors' => ['server' => [substr($e->getMessage(), 0, 200)]]], 422);
        }
    }
    
    public function uploadBatchPhotos(Request $request)
    {
        $request->validate([
            'photos.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'min:5', // Ukuran minimum dalam Kilobytes (100KB)
                'max:250'  // Ukuran maksimum dalam Kilobytes (300KB)
            ]
        ], [
            // Pesan error kustom agar lebih jelas
            'photos.*.min' => 'Ukuran file :attribute terlalu kecil (minimal 5KB).',
            'photos.*.max' => 'Ukuran file :attribute terlalu besar (maksimal 250KB).',
            'photos.*.mimes' => 'File :attribute harus berformat jpg, jpeg, atau png.',
            'photos.*.image' => 'File :attribute harus berupa gambar.',
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