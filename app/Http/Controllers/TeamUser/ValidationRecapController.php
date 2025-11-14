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
        // Query ini akan kita 'clone' untuk semua perhitungan statistik
        $baseHierarchyQuery = TemporaryMapping::query();
        
        if (!$user->hasRole('admin')) {
            // Filter untuk 'team' atau 'appuser' (siapapun yang bukan admin)
            $baseHierarchyQuery
                ->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($hierarchyFilter['column'], $hierarchyFilter['code']);
        }

        // ==================================================================
        // 2. QUERY STATISTIK SISTEM (KARTU ANGKA)
        // ==================================================================
        $lockExpirationTime = Carbon::now()->subMinutes(MappingValidationController::LOCK_TIMEOUT_MINUTES);

        $totalSystemData = (clone $baseHierarchyQuery)->count();
        
        $totalDataToValidate = (clone $baseHierarchyQuery)
            ->where('is_validated', false) //
            ->where(function($q) use ($lockExpirationTime) {
                // Sesuai logika di MappingValidationController@index
                $q->whereNull('locked_by')->orWhere('locked_at', '<', $lockExpirationTime);
            })
            ->count();
            
        $totalDataIsValidated = (clone $baseHierarchyQuery)
            ->where('is_validated', true)
            ->count();

        // Kumpulkan dalam satu objek untuk dikirim ke view
        $systemStats = (object) [
            'total_data_in_system' => $totalSystemData,
            'total_data_to_validate' => $totalDataToValidate,
            'total_data_is_validated' => $totalDataIsValidated,
        ];

        // ==================================================================
        // 3. QUERY REKAP PER-VALIDATOR (Tabel 1)
        // ==================================================================
        // Mulai query dasar (join users dan temporary_mappings)
        $validatorStatsQuery = DB::table('users')
            ->join('temporary_mappings', 'users.id', '=', 'temporary_mappings.user_validasi')
            ->select(
                'users.name',

                // Hitung total data beban kerja
                DB::raw("COUNT(temporary_mappings.id) as total_data"),

                // Hitung total data yang divalidasi
                DB::raw("COUNT(CASE WHEN temporary_mappings.is_validated = 1 THEN 1 END) as total_validated"),
                
                // Hitung yang menunggu review (sesuai query rekap)
                DB::raw("COUNT(CASE WHEN temporary_mappings.is_validated = 1 AND temporary_mappings.locked_by IS NULL THEN 1 END) as pending_review"),
                
                // Hitung total yang pernah ditolak
                DB::raw("COUNT(CASE WHEN temporary_mappings.ket_validasi LIKE 'rejected_%' THEN 1 END) as total_rejected"),
                
                // Bedah alasan penolakan dari kolom JSON 'validation_data'
                DB::raw("COUNT(CASE WHEN temporary_mappings.validation_data->>'$.eval_peta' = 'tidak' THEN 1 END) as rejected_peta"),
                DB::raw("COUNT(CASE WHEN temporary_mappings.validation_data->>'$.eval_persil' = 'tidak' THEN 1 END) as rejected_persil"),
                DB::raw("COUNT(CASE WHEN temporary_mappings.validation_data->>'$.eval_foto_kwh' = 'tidak' THEN 1 END) as rejected_foto_kwh")
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_validated', 'desc');

        // Terapkan filter hirarki/role pada query REKAP ANGKA
        if ($user->hasRole('appuser')) {
            // Appuser hanya lihat statistiknya sendiri
            $validatorStatsQuery->where('temporary_mappings.user_validasi', $user->id);
            
        } elseif ($user->hasRole('team')) {
            // Team Leader lihat statistik validator di wilayahnya
            // Kita perlu join ke master_data_pelanggan untuk filter wilayah
            $validatorStatsQuery
                ->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                ->where($hierarchyFilter['column'], $hierarchyFilter['code']); //
                
        } elseif (!$user->hasRole('admin')) {
             // Role lain (selain admin) tidak melihat apa-apa
             $validatorStatsQuery->where('temporary_mappings.id', 0); //
        }
        // Jika 'admin', tidak ada filter tambahan (melihat semua)

        // Eksekusi query statistik
        $validatorStats = $validatorStatsQuery->get();
        

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
            $query->join('master_data_pelanggan', 'temporary_mappings.idpel', '=', 'master_data_pelanggan.idpel')
                  ->select('temporary_mappings.*') //
                  ->where($hierarchyFilter['column'], $hierarchyFilter['code']) //
                  ->groupBy('temporary_mappings.id');
                  
        } elseif (!$user->hasRole('admin')) {
             // Role lain tidak melihat apa-apa
             $query->where('id', 0); //
        }
        // Jika 'admin', tidak ada filter tambahan
    
        $reviewItems = $query->paginate(10, ['*'], 'review_page');

        
        // ==================================================================
        // 3. KIRIM DATA KE VIEW
        // ==================================================================
        
        // Kirim kedua set data (Statistik dan Daftar Baris) ke view
        $viewData = compact('systemStats', 'validatorStats', 'reviewItems');

         if ($request->has('is_ajax')) {
            return view('team.validation_recap.partials.index_content', $viewData); //
        }

           return view('team.validation_recap.index', $viewData); //
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