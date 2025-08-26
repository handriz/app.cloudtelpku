<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterDataPelanggan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Jobs\ProcessMasterDataUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MasterDataController extends Controller
{
    public function dashboard()
    {
        $totalAktif = MasterDataPelanggan::where('STATUS_DIL', 'AKTIF')->count();
        $distribusiTarif = MasterDataPelanggan::select('TARIF', DB::raw('count(*) as total'))
                                                ->groupBy('TARIF')->get();

         return view('admin.manajemen_data.dashboard', compact('totalPelanggan')); // Variabel tambahan juga perlu di compact
    }

    public function index()
    {
        $dataPelanggan = MasterDataPelanggan::orderBy('IDPEL')->simplePaginate(20); // Paginasi 20 item per halaman
        return view('admin.manajemen_data.index', compact('dataPelanggan'));
    }

    public function uploadForm()
    {
        return view('admin.manajemen_data.upload');
    }

    /**
     * Menangani upload chunk file.
     * Menerima bagian-bagian kecil dari file yang diupload.
     */
    public function uploadChunk(Request $request)
    {
        // Validasi permintaan untuk memastikan semua data chunk lengkap
        $validator = Validator::make($request->all(), [
            'file_data' => 'required|file', // File chunk itu sendiri
            'resumableIdentifier' => 'required|string', // ID unik untuk sesi upload ini
            'resumableFilename' => 'required|string', // Nama file asli
            'resumableChunkNumber' => 'required|integer', // Nomor chunk saat ini
            'resumableTotalChunks' => 'required|integer', // Total jumlah chunk
        ]);

        if ($validator->fails()) {
            // Jika validasi gagal, kembalikan error JSON
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $file = $request->file('file_data'); // Dapatkan file chunk
        $identifier = $request->input('resumableIdentifier');
        $chunkNumber = $request->input('resumableChunkNumber');
        $totalChunks = $request->input('resumableTotalChunks');
        $filename = $request->input('resumableFilename');

        // Tentukan direktori sementara untuk menyimpan chunk
        $tempDir = 'uploads/chunks/' . $identifier;
        if (!Storage::exists($tempDir)) {
            // Buat direktori jika belum ada
            Storage::makeDirectory($tempDir);
        }

        // Simpan chunk dengan nama berdasarkan nomor chunk
        $chunkPath = $tempDir . '/' . $chunkNumber . '.part';
        Storage::put($chunkPath, file_get_contents($file->getRealPath()));

        Log::info("Chunk {$chunkNumber}/{$totalChunks} untuk {$filename} ({$identifier}) berhasil diupload.");

        return response()->json(['message' => 'Chunk berhasil diupload.']);
    }

    /**
     * Menggabungkan chunks menjadi file lengkap dan mengirim job ke queue.
     * Ini adalah titik pemicu untuk pemrosesan data ke database.
     */
    public function mergeChunks(Request $request)
    {
        // Validasi permintaan penggabungan chunk
        $validator = Validator::make($request->all(), [
            'resumableIdentifier' => 'required|string', // ID unik sesi upload
            'resumableFilename' => 'required|string', // Nama file asli
            'resumableTotalChunks' => 'required|integer', // Total jumlah chunk
            'resumableTotalSize' => 'required|integer', // Total ukuran file
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $identifier = $request->input('resumableIdentifier');
        $filename = $request->input('resumableFilename');
        $totalChunks = $request->input('resumableTotalChunks');
        $totalSize = $request->input('resumableTotalSize');

        $tempDir = 'uploads/chunks/' . $identifier;
        // Tentukan jalur file akhir setelah semua chunk digabungkan
        $finalPath = 'uploads/master_data_pelanggan/' . $filename; 

        // Verifikasi bahwa semua chunk yang diharapkan ada
        for ($i = 1; $i <= $totalChunks; $i++) {
            if (!Storage::exists($tempDir . '/' . $i . '.part')) {
                Log::error("Chunk {$i} hilang untuk file {$filename} ({$identifier}).");
                return response()->json(['error' => 'Satu atau lebih chunk hilang.'], 400);
            }
        }

        try {
            // Gabungkan semua chunk menjadi satu file
            $outputFile = Storage::path($finalPath); // Dapatkan jalur absolut
            $out = fopen($outputFile, 'wb'); // Buka file output dalam mode write binary

            if (!$out) {
                throw new \Exception("Tidak dapat membuka file akhir untuk penulisan.");
            }

            for ($i = 1; $i <= $totalChunks; $i++) {
                $chunkFile = Storage::path($tempDir . '/' . $i . '.part');
                $in = fopen($chunkFile, 'rb'); // Buka chunk dalam mode read binary
                if (!$in) {
                    throw new \Exception("Tidak dapat membuka file chunk {$i} untuk pembacaan.");
                }
                while ($buff = fread($in, 4096)) { // Baca dan tulis per buffer
                    fwrite($out, $buff);
                }
                fclose($in);
                Storage::delete($tempDir . '/' . $i . '.part'); // Hapus chunk setelah digabungkan
            }
            fclose($out); // Tutup file output

            // Verifikasi ukuran file akhir (opsional tapi disarankan)
            if (Storage::size($finalPath) != $totalSize) {
                Log::warning("Ukuran file tidak sesuai setelah penggabungan. Diharapkan: {$totalSize}, Aktual: " . Storage::size($finalPath));
                throw new \Exception("Ukuran file tidak sesuai setelah penggabungan.");
            }

            // Hapus direktori chunk sementara setelah berhasil digabungkan
            Storage::deleteDirectory($tempDir);
            
            // --- PENTING: MENGIRIM JOB KE QUEUE UNTUK PEMROSESAN DATA ---
            ProcessMasterDataUpload::dispatch($finalPath, auth()->id());

            Log::info("File {$filename} ({$identifier}) berhasil digabungkan dan job pemrosesan dikirim ke queue.");
            return response()->json(['message' => 'File berhasil digabungkan dan pemrosesan dimulai.']);

        } catch (\Exception $e) {
            Log::error("Gagal menggabungkan chunks untuk {$filename} ({$identifier}): " . $e->getMessage() . " di baris " . $e->getLine());
            // Tangani kegagalan: bersihkan sisa-sisa dan kembalikan error
            Storage::deleteDirectory($tempDir);
            if (Storage::exists($finalPath)) {
                Storage::delete($finalPath);
            }
            return response()->json(['error' => 'Gagal menggabungkan file: ' . $e->getMessage()], 500);
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
