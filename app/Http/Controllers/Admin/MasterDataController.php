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

        // Optimasi: Ambil data daya dalam satu query, lalu proses dengan Collection
        $distribusilayanan = $rekapData->groupBy('JENISLAYANAN')->map->sum('count');
        $pelangganByDaya = $rekapData->groupBy('DAYA')->map->sum('count');
        $pelangganPrabayarByDaya = $rekapData->where('JENISLAYANAN', 'Prabayar')->pluck('count', 'DAYA');
        $pelangganPaskabayarByDaya = $rekapData->where('JENISLAYANAN', 'Paskabayar')->pluck('count', 'DAYA');   

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
            'file' => 'required|file', // Asumsi nama input file dari JS adalah 'file'
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
        // Validasi permintaan penggabungan chunk
        $validated = $request->validate([
            'fileName' => 'required|string',
            'totalChunks' => 'required|integer',
            'totalSize' => 'required|integer',
        ]);

        $fileName = $validated['fileName'];
        $totalChunks = $validated['totalChunks'];
        $tempDir = 'temp_uploads/' . $fileName;
        $finalPath = 'imports/' . $fileName;

        try {
            // Pastikan direktori tujuan ada
            Storage::makeDirectory('imports');
            
            // Gabungkan semua chunk
            $finalFilePath = Storage::path($finalPath);
            $fileHandle = fopen($finalFilePath, 'w');

            for ($i = 0; $i < $totalChunks; $i++) { // Asumsi chunk index dimulai dari 0 dari JS kita
                $chunkPath = Storage::path($tempDir . '/' . $i);
                if (!file_exists($chunkPath)) {
                     throw new \Exception("Chunk {$i} hilang.");
                }
                fwrite($fileHandle, file_get_contents($chunkPath));
                unlink($chunkPath);
            }
            fclose($fileHandle);
            rmdir(Storage::path($tempDir));

            // Pastikan ukuran file sesuai
            if (Storage::size($finalPath) != $validated['totalSize']) {
                Storage::delete($finalPath); // Hapus file yang korup
                throw new \Exception("Ukuran file tidak sesuai setelah digabungkan.");
            }

            // Gunakan nama Job yang sesuai dengan kode Anda
            ProcessMasterDataUpload::dispatch($finalPath, auth()->id());

            Log::info("File {$fileName} berhasil digabungkan dan job dikirim ke queue.");
            return response()->json(['message' => 'File berhasil di-upload dan sedang diproses.']);

        } catch (\Exception $e) {
            Log::error("Gagal menggabungkan chunks untuk {$fileName}: " . $e->getMessage());
            Storage::deleteDirectory($tempDir);
            Storage::delete($finalPath);
            return response()->json(['error' => 'Gagal memproses file di server.'], 500);
        }
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

    
    /**
     * Menghapus satu data pelanggan.
     */
    public function destroy(MasterDataPelanggan $pelanggan)
    {
        $pelanggan->delete();
        return redirect()->route('admin.manajemen_data.index')->with('success', 'Data Pelanggan berhasil dihapus!');
    }
    
}
