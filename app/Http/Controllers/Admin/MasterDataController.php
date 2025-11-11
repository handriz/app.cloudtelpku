<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterDataPelanggan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessPelangganImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\HierarchyLevel;


class MasterDataController extends Controller
{
    public function dashboard(Request $request)
    {
        $rekapData = MasterDataPelanggan::query()
            ->select('JENISLAYANAN', 'DAYA', DB::raw('count(*) as count'))
            ->groupBy('JENISLAYANAN', 'DAYA')
            ->orderBy('DAYA')
            ->get();
        $latestBulanRekap = MasterDataPelanggan::max('V_BULAN_REKAP');
        $totalPelanggan = MasterDataPelanggan::where('STATUS_DIL', 'AKTIF')->count();
        $distribusilayanan = $rekapData->groupBy('JENISLAYANAN')->map->sum('count');

        $sortableColumns = ['DAYA', 'total_pelanggan'];
        $sortColumn = $request->input('sort', 'DAYA');
        $sortDirection = $request->input('direction', 'asc');

        // Validasi untuk keamanan
        if (!in_array($sortColumn, $sortableColumns)) {
            $sortColumn = 'DAYA';
        }

        $query = DB::table('master_data_pelanggan')
            ->select('DAYA', DB::raw('count(*) as total_pelanggan'))
            ->groupBy('DAYA');
        if ($sortColumn === 'DAYA') {
            // Jika sorting berdasarkan DAYA, paksa untuk diurutkan sebagai angka
            $query->orderByRaw('CAST(DAYA AS UNSIGNED) ' . $sortDirection);
        } else {
            // Jika sorting berdasarkan total_pelanggan, gunakan orderBy biasa
            $query->orderBy($sortColumn, $sortDirection);
        }

        $pelangganByDaya = $query->paginate(10)->withQueryString();

        $pelangganPrabayarByDaya = $rekapData->where('JENISLAYANAN', 'PRABAYAR')->pluck('count', 'DAYA');
        $pelangganPaskabayarByDaya = $rekapData->where('JENISLAYANAN', 'PASKABAYAR')->pluck('count', 'DAYA');   

        $viewData = compact(
            'totalPelanggan',
            'distribusilayanan',
            'pelangganByDaya',
            'pelangganPrabayarByDaya',
            'pelangganPaskabayarByDaya',
            'latestBulanRekap'
        );

         if ($request->has('is_ajax')) {
            // Jika request datang dari klik menu, kirim hanya kontennya
            return view('admin.manajemen_data.partials.dashboard_content', $viewData);
        }

           return view('admin.manajemen_data.dashboard', $viewData);
   }

    public function index()
    {
        $dataPelanggan = MasterDataPelanggan::orderBy('IDPEL')->simplePaginate(20); // Paginasi 20 item per halaman
        return view('admin.manajemen_data.index', compact('dataPelanggan'));
    }

    public function uploadForm()
    {
         return view('admin.manajemen_data.partials.upload_form');
    }

    /**
     * Menangani upload chunk file.
     * Menerima bagian-bagian kecil dari file yang diupload.
     */
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

    /**
     * Menggabungkan chunks menjadi file lengkap dan mengirim job ke queue.
     * Ini adalah titik pemicu untuk pemrosesan data ke database.
     */
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

        ob_start();

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

            ob_end_clean();

            Log::info("File {$fileName} berhasil digabungkan dan job dikirim oleh user ID: " . auth()->id());
            
            return response()->json(['message' => 'File berhasil di-upload dan sedang diproses di latar belakang.']);

        } catch (\Exception $e) {

            ob_end_clean();
            
            Log::error("Gagal menggabungkan chunks untuk {$fileName}: " . $e->getMessage());
            // Bersihkan file sisa jika terjadi error
            Storage::deleteDirectory($tempDir);
            Storage::delete($finalPath); // Hapus juga file final yang mungkin korup
            return response()->json(['error' => 'Gagal memproses file di server.'], 500);
        }
    }


    public function downloadFormat()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="format_upload_master_pelanggan.csv"',
        ];

        // Ambil daftar kolom langsung dari model untuk memastikan selalu sinkron
        $columns = (new MasterDataPelanggan)->getFillable();

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            // Tulis header ke file CSV dengan delimiter titik koma (;)
            fputcsv($file, $columns, ';');
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Menampilkan formulir untuk mengedit satu data pelanggan.
     */
    public function edit(MasterDataPelanggan $pelanggan) // Route Model Binding
    {
        return view('admin.manajemen_data.edit', compact('pelanggan'));
    }

    /**
     * Memperbarui satu data pelanggan.
     */
    public function update(Request $request, MasterDataPelanggan $pelanggan)
    {
        // Aturan validasi untuk update data pelanggan
        $request->validate([
            'V_BULAN_REKAP' => 'nullable|string|max:255',
            'UNITUPI' => 'nullable|string|max:255',
            'UNITAP' => 'nullable|string|max:255',
            'UNITUP' => 'nullable|string|max:255',
            'IDPEL' => 'required|string|max:255|unique:master_data_pelanggan,IDPEL,' . $pelanggan->id, // IDPEL harus unik, kecuali untuk data ini sendiri
            'TARIF' => 'nullable|string|max:255',
            'DAYA' => 'nullable|string|max:255',
            'KOGOL' => 'nullable|string|max:255',
            'KDDK' => 'nullable|string|max:255',
            'NOMOR_METER_KWH' => 'nullable|string|max:255',
            'MERK_METER_KWH' => 'nullable|string|max:255',
            'TAHUN_TERA_METER_KWH' => 'nullable|string|max:255',
            'TAHUN_BUAT_METER_KWH' => 'nullable|string|max:255',
            'CT_PRIMER_KWH' => 'nullable|string|max:255',
            'CT_SEKUNDER_KWH' => 'nullable|string|max:255',
            'PT_PRIMER_KWH' => 'nullable|string|max:255',
            'PT_SEKUNDER_KWH' => 'nullable|string|max:255',
            'FKMKWH' => 'nullable|string|max:255',
            'JENISLAYANAN' => 'nullable|string|max:255',
            'STATUS_DIL' => 'nullable|string|max:255',
            'NOMOR_GARDU' => 'nullable|string|max:255',
            'NAMA_GARDU' => 'nullable|string|max:255',
            'KOORDINAT_X' => 'nullable|numeric|regex:/^-?\d+(\.\d{1,8})?$/', // Memastikan format desimal
            'KOORDINAT_Y' => 'nullable|numeric|regex:/^-?\d+(\.\d{1,8})?$/',
            'KDPEMBMETER' => 'nullable|string|max:255',
            'KDAM' => 'nullable|string|max:255',
            'VKRN' => 'nullable|string|max:255',
        ]);

        $pelanggan->update($request->all());

        return redirect()->route('admin.manajemen_data.index')->with('success', 'Data Pelanggan berhasil diperbarui!');
    }

    public function destroy(MasterDataPelanggan $pelanggan)
    {
        $pelanggan->delete();
        return redirect()->route('admin.manajemen_data.index')->with('success', 'Data Pelanggan berhasil dihapus!');
    }
    
    public function checkIdpelExistsAjax(Request $request, $idpel)
    {
        // Validasi dasar: Pastikan IDPEL 12 digit numerik
        if (!preg_match('/^\d{12}$/', $idpel)) {
            return response()->json(['exists' => false, 'message' => 'Format ID Pelanggan tidak valid.'], 400); // Bad Request
        }

        $user = Auth::user();
        $query = MasterDataPelanggan::where('idpel', $idpel);

        // Terapkan filter hirarki HANYA untuk non-admin
        if (!$user->hasRole('admin')) {
            $hierarchyFilter = $this->getHierarchyFilterForMaster($user); // Helper function (lihat di bawah)
            
            if ($hierarchyFilter) {
                // Pastikan filter diterapkan dengan benar
                $query->where($hierarchyFilter['column'], $hierarchyFilter['code']);
                $level = HierarchyLevel::where('code', $hierarchyFilter['code'])->first();
                $hierarchyName = $level ? $level->name : $hierarchyFilter['code']; // Fallback ke kode jika nama tidak ada
            } else {
                 // Jika user tidak punya hirarki, cegah dia melihat data apa pun
                return response()->json(['exists' => false, 'message' => 'User tidak memiliki hak akses hirarki.'], 403); // Forbidden
            }
        }

        $pelanggan = $query->select('idpel', 'status_dil')->first();

        $exists = !is_null($pelanggan);
        $statusDil = $exists ? $pelanggan->status_dil : null;
        $isActive = $exists && strtoupper($statusDil) === 'AKTIF';

        $message = '';
        if ($exists) {
            $message = $isActive ? 'ID Pelanggan ditemukan (Status: AKTIF).' : 'ID Pelanggan ditemukan (Status: NON AKTIF).';
        } else {
            $message = 'ID Pelanggan tidak ditemukan di Master Data (' . $hierarchyName . ').';
        }

        return response()->json([
            'exists' => $exists,
            'status_dil' => $statusDil, 
            'is_active' => $isActive, 
            'message' => $message
        ]);
    }

    private function getHierarchyFilterForMaster($user): ?array
    {
        if ($user->hasRole('admin')) return null;

        $userHierarchyCode = $user->hierarchy_level_code;
        if (!$userHierarchyCode) return null; // Atau return ['column' => 'id', 'code' => -1]; jika ingin lebih strict

        $level = HierarchyLevel::where('code', $userHierarchyCode)->with('parent.parent')->first();
        if (!$level) return null; // Atau return ['column' => 'id', 'code' => -1];

        // Sesuaikan nama kolom dengan tabel master_data_pelanggan
        if ($level->parent_code === null) {
            return ['column' => 'unitupi', 'code' => $userHierarchyCode];
        } elseif ($level->parent && $level->parent->parent_code === null) {
            return ['column' => 'unitap', 'code' => $userHierarchyCode];
        }
        return ['column' => 'unitup', 'code' => $userHierarchyCode];
    }
}
