<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use App\Models\TemporaryMapping;
use App\Models\MappingKddk;
use App\Models\MasterDataPelanggan;
use App\Models\HierarchyLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\TeamUser\MappingValidationController;

class ValidationRecapController extends Controller 
{
    /**
     * Menampilkan daftar item yang siap untuk di-review (is_validated = true).
     */
public function index(Request $request)
    {
        $user = Auth::user();
        $hierarchyFilter = $this->getHierarchyFilterForJoin($user); //

        // ==================================================================
        // 1. MEMBUAT QUERY DASAR UNTUK HIRARKI (PERFORMA)
        // ==================================================================
        $lockExpirationTime = Carbon::now()->subMinutes(MappingValidationController::LOCK_TIMEOUT_MINUTES);
        $baseHierarchyQuery = TemporaryMapping::query();
        
        if (!$user->hasRole('admin')) {
            // Filter untuk 'team' atau 'appuser' (siapapun yang bukan admin)
            $baseHierarchyQuery
                ->leftJoin('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
        }

        // ==================================================================
        // 2. QUERY STATISTIK SISTEM (KARTU ANGKA)
        // ==================================================================

        $totalSystemData = (clone $baseHierarchyQuery)->count();
        $totalDataToValidate = (clone $baseHierarchyQuery)
            ->where('is_validated', false) 
            ->where(function($q) use ($lockExpirationTime) {
                $q->whereNull('locked_by')->orWhere('locked_at', '<', $lockExpirationTime);
            })
            ->count();
            
        $totalDataIsValidated = (clone $baseHierarchyQuery)
            ->where('is_validated', true)
            ->count();

        $systemStats = (object) [
            'total_data_in_system' => $totalSystemData,
            'total_data_to_validate' => $totalDataToValidate,
            'total_data_is_validated' => $totalDataIsValidated,
        ];

        // ==================================================================
        // 2. QUERY REKAP PER-VALIDATOR (Tabel 1: Angka Kinerja)
        // ==================================================================
        $validatorStatsQuery = DB::table('temporary_mappings')
            ->leftJoin('users', 'temporary_mappings.user_validasi', '=', 'users.id')
            ->where(function ($query) {
                $query->where('temporary_mappings.is_validated', true)
                      ->orWhere('temporary_mappings.ket_validasi', 'LIKE', 'rejected_%');
            })
            ->select(
                DB::raw("COALESCE(users.name, 'Validator Tidak Dikenal') as name"),
                'temporary_mappings.user_validasi as user_id',

                // Total Beban Kerja (Total Event Validated/Rejected)
                DB::raw("COUNT(temporary_mappings.id) as total_data"),

                // Total Divalidasi (Hanya hitung jika final statusnya 'verified')
                DB::raw("COUNT(CASE WHEN temporary_mappings.is_validated = 1 THEN 1 END) as total_validated"),

                // Hitung yang menunggu review (is_validated=true dan tidak terkunci)
                DB::raw("COUNT(CASE WHEN temporary_mappings.is_validated = 1 AND temporary_mappings.locked_by IS NULL THEN 1 END) as pending_review"),
                
                // Hitung total yang pernah ditolak
                DB::raw("COUNT(CASE WHEN temporary_mappings.ket_validasi LIKE 'rejected_%' OR temporary_mappings.ket_validasi = 'review_rejected' THEN 1 END) as total_rejected"),
                
                // Bedah alasan penolakan dari kolom JSON 'validation_data'
                DB::raw("COUNT(CASE WHEN temporary_mappings.validation_data->>'$.eval_peta' = 'tidak' THEN 1 END) as rejected_peta"),
                DB::raw("COUNT(CASE WHEN temporary_mappings.validation_data->>'$.eval_persil' = 'tidak' THEN 1 END) as rejected_persil"),
                DB::raw("COUNT(CASE WHEN temporary_mappings.validation_data->>'$.eval_foto_kwh' = 'tidak' THEN 1 END) as rejected_foto_kwh")
            )
            ->groupBy('temporary_mappings.user_validasi', 'users.name')
            ->orderBy('total_validated', 'asc');

        
            // Terapkan filter hirarki/role pada query REKAP ANGKA
        if ($user->hasRole('appuser')) {
            // Appuser hanya lihat statistiknya sendiri
            $validatorStatsQuery->where('temporary_mappings.user_validasi', $user->id);
            
        } elseif ($user->hasRole('team')) {
            // Team Leader lihat statistik validator di wilayahnya
            // Kita perlu join ke master_data_pelanggan untuk filter wilayah
            $validatorStatsQuery
                ->leftJoin('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($hierarchyFilter['column'], $hierarchyFilter['code']); //
                
        } elseif (!$user->hasRole('admin')) {
             // Role lain (selain admin) tidak melihat apa-apa
             $validatorStatsQuery->where('temporary_mappings.id', 0); //
        }
        // Jika 'admin', tidak ada filter tambahan (melihat semua)

        // Ambil hasil query (ini yang digunakan untuk tabel dan Grand Total)
        $validatorStats = $validatorStatsQuery->get();
        
        // Perhitungan Grand Total (FIX 2: Menjumlahkan hasil dari query yang sudah difilter)
        $grandTotals = [
            'total_data'       => $validatorStats->sum('total_data'),
            'total_validated'  => $validatorStats->sum('total_validated'),
            'pending_review'   => $validatorStats->sum('pending_review'),
            'total_rejected'   => $validatorStats->sum('total_rejected'),
            'rejected_peta'    => $validatorStats->sum('rejected_peta'),
            'rejected_persil'  => $validatorStats->sum('rejected_persil'),
            'rejected_foto_kwh'=> $validatorStats->sum('rejected_foto_kwh'),
        ];

        // ==================================================================
        // 2. QUERY DAFTAR TUGAS PER BARIS (UNTUK DI-REVIEW)
        // ==================================================================
        
        $query = TemporaryMapping::query()
            ->where('is_validated', true) //
            ->whereNull('locked_by') //
            ->orderBy('updated_at', 'asc')
            ->with('validator'); // Load relasi validator

        // Terapkan filter hirarki/role pada query DAFTAR BARIS
        if ($user->hasRole('appuser')) {
            // Appuser hanya lihat riwayat validasinya sendiri
            $query->where('user_validasi', $user->id); //
            
        } elseif ($user->hasRole('team')) {
            // Team Leader lihat daftar review di wilayahnya
            $query->leftJoin('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                ->select('temporary_mappings.*') //
                ->where($hierarchyFilter['column'], $hierarchyFilter['code']) ;              
                  
        } elseif (!$user->hasRole('admin')) {
             // Role lain tidak melihat apa-apa
             $query->where('id', 0); //
        }
        // Jika 'admin', tidak ada filter tambahan
    
        $reviewItems = $query->paginate(10, ['*'], 'review_page')->onEachSide(1);

        
        // ==================================================================
        // 3. KIRIM DATA KE VIEW
        // ==================================================================
        
        // Kirim kedua set data (Statistik dan Daftar Baris) ke view
        $viewData = compact('systemStats', 'validatorStats', 'reviewItems','grandTotals');

         if ($request->has('is_ajax')) {
            return view('team.validation_recap.partials.index_content', $viewData); //
        }

           return view('team.validation_recap.index', $viewData); //
   }

   public function downloadValidatorReport(Request $request)
    {
        $request->validate([
            'metric' => 'required|string',
            'user_id' => 'nullable', // Bisa 'NULL' (string) atau ID
            'format' => 'nullable|string|in:csv,excel',
        ]);

        $metric = $request->input('metric');
        $userId = $request->input('user_id');
        $format = $request->input('format', 'csv');

        $user = Auth::user();
        $hierarchyFilter = $this->getHierarchyFilterForJoin($user);

        // 1. Buat Query dasar untuk mengambil DATA BARIS
        // Query ini harus mereplikasi filter hirarki dari 'index'
        $query = TemporaryMapping::query()
            ->with('validator'); // Load relasi validator

        // Filter berdasarkan Validator
        if ($userId === 'NULL' || $userId === null) {
            $query->whereNull('user_validasi');
        } else {
            $query->where('user_validasi', $userId);
        }

        // Filter berdasarkan Hirarki Supervisor
        if ($user->hasRole('team')) {
            $query->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                  ->select('temporary_mappings.*') //
                  ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
        } elseif ($user->hasRole('appuser')) {
             $query->where('temporary_mappings.user_validasi', $user->id);
        } elseif (!$user->hasRole('admin')) {
             $query->where('temporary_mappings.id', 0); //
        }

        // 2. Terapkan filter METRIK (harus cocok dengan query 'index')
        switch ($metric) {
            case 'total_data':
                // Tidak perlu filter tambahan
                break;
            case 'total_validated':
                $query->where('is_validated', true);
                break;
            case 'pending_review':
                $query->where('is_validated', true)->whereNull('locked_by');
                break;
            case 'total_rejected':
                $query->where('ket_validasi', 'LIKE', 'rejected_%');
                break;
            case 'rejected_peta':
                $query->where('validation_data->eval_peta', 'tidak');
                break;
            case 'rejected_persil':
                $query->where('validation_data->eval_persil', 'tidak');
                break;
            case 'rejected_foto_kwh':
                $query->where('validation_data->eval_foto_kwh', 'tidak');
                break;
            default:
                // Jika metrik tidak dikenal, batalkan.
                return redirect()->back()->with('error', 'Metrik unduhan tidak dikenal.');
        }

        // === MULAI LOGIKA EXPORT ===
        
        $timestamp = Carbon::now()->format('YmdHi');
        $columns = [
            'IDPEL', 'OBJECTID', 'Validator', 'Status', 'Tgl Update', 
            'Latitude Y', 'Longitude X', 
            'Catatan Validasi', 'Alasan Tolak Peta', 'Alasan Tolak Persil', 'Alasan Tolak Foto KWH'
        ];

        // --- OPSI 1: FORMAT CSV (Default) ---
        if ($format === 'csv') {
            $fileName = "rekap_{$metric}_{$timestamp}.csv";
            $headers = [
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
                "Pragma" => "no-cache", "Expires" => "0"
            ];

            $callback = function() use($query, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);
                $query->chunk(500, function($items) use ($file) {
                    foreach ($items as $item) {
                        fputcsv($file, $this->mapRowData($item)); // Gunakan helper
                    }
                });
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        }

        // --- OPSI 2: FORMAT EXCEL (HTML Table Stream) ---
        if ($format === 'excel') {
            $fileName = "rekap_{$metric}_{$timestamp}.xls"; // Gunakan .xls agar Excel membukanya
            $headers = [
                "Content-Type" => "application/vnd.ms-excel", // MIME type untuk Excel
                "Content-Disposition" => "attachment; filename=\"$fileName\"",
                "Pragma" => "no-cache", "Expires" => "0"
            ];

            $callback = function() use($query, $columns) {
                $file = fopen('php://output', 'w');
                
                // Tulis Header HTML Table
                fwrite($file, "<html><head><meta charset='UTF-8'></head><body>");
                fwrite($file, "<table border='1'>");
                
                // Tulis Judul Kolom
                fwrite($file, "<thead><tr>");
                foreach ($columns as $col) {
                    fwrite($file, "<th style='background-color:#f0f0f0; font-weight:bold;'>{$col}</th>");
                }
                fwrite($file, "</tr></thead><tbody>");

                // Tulis Data
                $query->chunk(500, function($items) use ($file) {
                    foreach ($items as $item) {
                        $row = $this->mapRowData($item); // Gunakan helper
                        fwrite($file, "<tr>");
                        foreach ($row as $cell) {
                            // Pastikan data aman untuk HTML
                            $cellData = htmlspecialchars($cell ?? '', ENT_QUOTES, 'UTF-8');
                            // Paksa format teks untuk angka panjang (seperti IDPEL) agar tidak jadi eksponen
                            fwrite($file, "<td style='mso-number-format:\"\@\";'>{$cellData}</td>");
                        }
                        fwrite($file, "</tr>");
                    }
                });

                fwrite($file, "</tbody></table></body></html>");
                fclose($file);
            };
            return response()->stream($callback, 200, $headers);
        }
    }

    private function mapRowData($item) {
        return [
            $item->idpel,
            $item->objectid,
            $item->validator->name ?? 'TIDAK DIKENAL',
            $item->ket_validasi,
            $item->updated_at->format('Y-m-d H:i:s'),
            $item->latitudey,
            $item->longitudex,
            $item->validation_notes,
            $item->validation_data['eval_peta_reason'] ?? ($item->validation_data['eval_peta'] ?? ''),
            $item->validation_data['eval_persil_reason'] ?? ($item->validation_data['eval_persil'] ?? ''),
            $item->validation_data['eval_foto_kwh_reason'] ?? ($item->validation_data['eval_foto_kwh'] ?? '')
        ];
    }
    
    /**
     * Menyetujui (Promote) data dari temporary_mappings ke mapping_kddk.
     */
    public function promote(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $tempData = TemporaryMapping::where('id', $id)
                        ->where('is_validated', true)
                        ->lockForUpdate() 
                        ->firstOrFail();

            $idpel = $tempData->idpel;
            $objectid = $tempData->objectid;

            // 1. Salin data & atur status
            $validatedData = $tempData->toArray();
            $validatedData['ket_validasi'] = 'verified'; 
            $validatedData['enabled'] = true; 

            // 2. Pindahkan foto
            $newPhotoPaths = [];
            $disk = Storage::disk('public');

            foreach (['foto_kwh', 'foto_bangunan'] as $photoType) {
                $oldPath = $tempData->{$photoType};
                if ($oldPath && $disk->exists($oldPath)) {
                    $newPath = str_replace('unverified', 'verified', $oldPath);
                    $disk->move($oldPath, $newPath);
                    $newPhotoPaths[$photoType] = $newPath;
                }
            }

            // 3. Bersihkan data array sebelum pindah
            unset(
                $validatedData['id'], $validatedData['locked_by'], $validatedData['locked_at'],
                $validatedData['is_validated'], $validatedData['created_at'], $validatedData['updated_at'],
                $validatedData['user_validasi'], 
                $validatedData['validation_data'], $validatedData['validation_notes'],
                $validatedData['foto_kwh'], $validatedData['foto_bangunan']  
            );

            // 4. Nonaktifkan data LAMA (Supersede)
            MappingKddk::where('idpel', $idpel)
                       ->where('objectid', '!=', $objectid) 
                       ->where('enabled', true)
                       ->update([
                            'enabled' => false,
                            'ket_validasi' => 'superseded'
                        ]);

            // 5. Buat atau Update entri di tabel mapping_kddk
            MappingKddk::updateOrCreate(
                ['objectid' => $objectid], 
                array_merge($validatedData, $newPhotoPaths) 
            );

            // 6. Hapus entri dari tabel temporary
            $tempData->delete();

            DB::commit();

            return back()->with('success', 'Data IDPEL ' . $idpel . ' (Object ID: ' . $objectid . ') berhasil di-approve dan dipromosikan!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal promosi data review ID {$id}: " . $e->getMessage());
            return back()->with('error', 'Gagal mempromosikan data: ' . $e->getMessage());
        }
    }

    /**
     * Menolak (Reject Review) data, mengembalikan ke antrian validasi App User.
     */
    public function rejectReview(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:10']);

        DB::beginTransaction();
        try {
            $tempData = TemporaryMapping::where('id', $id)
                        ->where('is_validated', true)
                        ->lockForUpdate() 
                        ->firstOrFail();

            // Kembalikan status ke Rejected_X dan reset flag
            $tempData->is_validated = false;
            $tempData->ket_validasi = 'review_rejected'; 
            $tempData->validation_notes = "Review Ditolak oleh Supervisor: " . $request->input('reason');
            $tempData->locked_by = null;
            $tempData->locked_at = null;

            $tempData->save();
            DB::commit();

            return back()->with('success', 'Data IDPEL ' . $tempData->idpel . ' dikembalikan ke antrian validasi App User.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal reject review ID {$id}: " . $e->getMessage());
            return back()->with('error', 'Gagal menolak review: ' . $e->getMessage());
        }
    }

    public function showRepairModal()
    {
        // Fungsi ini hanya mengembalikan HTML partial untuk modal
        return view('team.validation_recap.partials._repair_search_modal');
    }

    public function findRepairData(Request $request)
    {
        $searchId = $request->input('search_id');
        $currentUser = Auth::user();
        $isAdminOrTeam = $currentUser->hasRole('admin') || $currentUser->hasRole('team');

        if (empty($searchId)) {
            return '<div class="p-4 bg-red-100 text-red-700 rounded-md">IDPEL atau OBJECTID tidak boleh kosong.</div>';
        }

        $item = null;
        $sourceTable = null;

        // 1. Cek di TemporaryMapping (Prioritas)
        $item = TemporaryMapping::where('idpel', $searchId)
                                ->orWhere('objectid', $searchId)
                                ->first();
        if ($item) {
            $sourceTable = 'temporary_mappings';
        } else {
            // 2. Cek di MappingKddk (Data Final)
            $item = MappingKddk::where('idpel', $searchId)
                              ->orWhere('objectid', $searchId)
                              ->first();
            if ($item) $sourceTable = 'mapping_kddk';
        }

        // --- PENGECEKAN 1: DATA TIDAK DITEMUKAN ---
        if (!$item) {
            return '<div class="p-4 bg-yellow-100 text-yellow-700 rounded-md"><i class="fas fa-exclamation-circle mr-1"></i> Data tidak ditemukan di tabel manapun.</div>';
        }

        // --- PENGECEKAN 2: DATA SUDAH VALID/FINAL (ATURAN NO. 1) ---
        // Jika sumbernya mapping_kddk, berarti sudah final.
        // Jika sumbernya temporary tapi is_validated=true, berarti sudah valid (tunggu review).
        $isAlreadyValid = ($sourceTable === 'mapping_kddk') || ($sourceTable === 'temporary_mappings' && $item->is_validated);

        if ($isAlreadyValid && !$isAdminOrTeam) {
            // Jika data sudah valid/final DAN user BUKAN Admin/TL, blokir akses.
            return '
                <div class="p-4 bg-blue-100 text-blue-700 rounded-md">
                    <h4 class="font-bold"><i class="fas fa-check-circle mr-1"></i> Data Sudah Valid/Final</h4>
                    <p class="text-sm mt-1">Data ini hanya bisa dikoreksi oleh Team Leader atau Admin.</p>
                </div>';
        }
        
        // --- PENGECEKAN 3: OTORISASI KEPEMILIKAN (ATURAN NO. 2) ---
        // Kita hanya perlu cek kepemilikan jika data BELUM valid DAN user BUKAN Admin/TL
        if (!$isAlreadyValid) {
            $isOwner = $item->user_validasi == $currentUser->id;

            if (!$isAdminOrTeam && !$isOwner) {
                $ownerName = $item->validator ? $item->validator->name : 'Validator Lain';
                
                return '
                    <div class="p-4 bg-red-100 text-red-700 rounded-md">
                        <h4 class="font-bold"><i class="fas fa-lock mr-1"></i> Akses Ditolak</h4>
                        <p class="text-sm mt-1">Data ini hanya bisa diperbaiki oleh validator aslinya (<strong>' . $ownerName . '</strong>), TL, atau Admin.</p>
                    </div>';
            }
        }

        // Jika lolos semua cek, tampilkan form
        return view('team.validation_recap.partials._repair_edit_form', compact('item', 'sourceTable'));
    }

    public function updateRepairData(Request $request)
    {
        // Validasi
        $validated = $request->validate([
            'source_table' => 'required|string',
            'item_id'      => 'required|integer',
            'idpel'        => 'required|string|max:255',
            'nokwhmeter'   => 'nullable|string|max:255',
            'latitudey'    => 'nullable|numeric',
            'longitudex'   => 'nullable|numeric',
            'sr'            => 'nullable|string|max:255',
            'latitudey_sr'  => 'nullable|numeric',
            'longitudex_sr' => 'nullable|numeric',
            'foto_kwh_new'      => 'nullable|image|max:5120',
            'foto_bangunan_new' => 'nullable|image|max:5120',
        ]);

        $currentUser = Auth::user();
        $isAdminOrTeam = $currentUser->hasRole('admin') || $currentUser->hasRole('team');
        $model = null;

        if ($validated['source_table'] === 'temporary_mappings') {
            $model = TemporaryMapping::find($validated['item_id']);
        } elseif ($validated['source_table'] === 'mapping_kddk') {
            $model = MappingKddk::find($validated['item_id']);
        }

        if (!$model) {
            return response()->json(['message' => 'Gagal menemukan data.'], 404);
        }

        // Cek Status Awal (digunakan untuk logika update status)
        $isAlreadyValidated = $model->is_validated;

        // --- CHECK KEAMANAN SERVER (REDUNDAN TAPI WAJIB) ---
        $isOwner = $model->user_validasi == $currentUser->id;

        // 1. Cek otorisasi akses secara umum
        if (!$isAdminOrTeam && !$isOwner) {
            return response()->json(['message' => 'Akses Ditolak: Anda tidak memiliki izin untuk mengedit data.'], 403);
        }

        // 2. Cek izin ganti foto (ATURAN BARU: HANYA Admin/TL yang bisa ganti foto)
        if (!$isAdminOrTeam && ($request->hasFile('foto_kwh_new') || $request->hasFile('foto_bangunan_new'))) {
             return response()->json(['message' => 'Akses Ditolak: Hanya Admin atau Team Leader yang diizinkan mengganti foto.'], 403);
        }

        // 3. Cek data final (MappingKddk hanya bisa diubah Admin/TL)
        if ($validated['source_table'] === 'mapping_kddk' && !$isAdminOrTeam) {
            return response()->json(['message' => 'Akses Ditolak: Data final hanya bisa diubah Admin/TL.'], 403);
        }
        // --- BATAS CEK KEAMANAN ---
        
        DB::beginTransaction();
        try {
            // 1. Update Data Teknis
            $model->idpel = $validated['idpel'];
            $model->nokwhmeter = $validated['nokwhmeter'];
            $model->latitudey = $validated['latitudey'];
            $model->longitudex = $validated['longitudex'];
            $model->sr = $validated['sr'];
            $model->latitudey_sr = $validated['latitudey_sr'];
            $model->longitudex_sr = $validated['longitudex_sr'];

            // 2. LOGIKA STATUS & VALIDATOR
            if ($validated['source_table'] === 'temporary_mappings') {
                
                if ($isAlreadyValidated) {
                    // ATURAN NO. 2 (Koreksi Data Valid oleh Admin/TL): user_validasi TIDAK BERUBAH.
                    $model->ket_validasi = 'corrected_by_supervisor'; // Tandai telah diperbaiki
                    // user_validasi dan is_validated TIDAK DISENTUH
                } else {
                    // KONDISI: Data Ditolak/Pending diperbaiki oleh pemilik/Admin
                    $model->is_validated = true; 
                    $model->ket_validasi = 'corrected_by_owner'; 
                    // Assign credit ke user yang login, karena dialah yang memperbaiki.
                    $model->user_validasi = $currentUser->id; 
                    $model->validation_notes = "Data diperbaiki manual oleh " . $currentUser->name;
                }
            } 
            // Jika 'mapping_kddk', status/validator TIDAK DISENTUH.

            // 3. Helper Ganti Foto (Tidak berubah)
            $disk = Storage::disk('public');
            $handlePhotoReplacement = function ($fileInputName, $dbColumnName, $suffixName) use ($request, $model, $disk) {
                if ($request->hasFile($fileInputName)) {
                    $file = $request->file($fileInputName);
                    $oldPath = $model->{$dbColumnName};
                    $targetDir = $oldPath ? dirname($oldPath) : "mapping_photos/unverified/{$model->idpel}";
                    $extension = $file->getClientOriginalExtension();
                    $newFileName = "{$model->objectid}_{$model->idpel}_{$suffixName}.{$extension}";
                    $newPath = "{$targetDir}/{$newFileName}";

                    if ($oldPath && $disk->exists($oldPath)) $disk->delete($oldPath);
                    $disk->putFileAs($targetDir, $file, $newFileName);
                    $model->{$dbColumnName} = $newPath;
                }
            };

            if ($isAdminOrTeam) {
                $handlePhotoReplacement('foto_kwh_new', 'foto_kwh', 'foto_app');
                $handlePhotoReplacement('foto_bangunan_new', 'foto_bangunan', 'foto_persil');
            }

            $model->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbaiki dan status dipertahankan/diperbarui!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gagal update repair data: " . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /**
     * Helper untuk filter hirarki (Salin dari MappingValidationController)
     */
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