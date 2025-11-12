<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\TemporaryMapping; 

class ProcessPhotoInbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // Signature: nama command dan opsi --limit
    protected $signature = 'photos:process-inbox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pindai folder inbox foto sementara, pindahkan ke folder unverified, dan update path di database.';

    // Path relatif terhadap storage/app/
    private $inboxPath = 'temp_photo_uploads';
    // Path dasar tujuan di disk 'public' (storage/app/public/...)
    private $destinationBase = 'public/mapping_photos/unverified';
    // Path dasar yang akan disimpan ke database (tanpa 'public/')
    private $dbPathBase = 'mapping_photos/unverified';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // --- PERBAIKAN TYPO 1 ---
        $limit = (int) $this->option('limit');
        // --- PERBAIKAN TYPO 2 ---
        $this->info("Memulai pemrosesan inbox foto (Limit: {$limit})...");

        // Variabel untuk monitoring
        $totalProcessed = 0;
        $totalSuccessMove = 0;
        $totalSuccessDbUpdate = 0;
        $totalFailedMove = 0;
        $totalFailedDbUpdate = 0;
        $totalSkipped = 0;
        $startTime = microtime(true);
        $diskPublic = Storage::disk('public');

        // 1. Dapatkan daftar file di inbox (disk default 'local')
        $files = Storage::files($this->inboxPath);
        $totalFilesInInbox = count($files);
        $this->info("Ditemukan {$totalFilesInInbox} file di inbox.");

        // Update status awal di cache untuk monitoring
        Cache::put('photo_processing_status', [
            'running' => true,
            'last_run' => now()->toDateTimeString(),
            'total_inbox' => $totalFilesInInbox,
            'processed_this_run' => 0,
            'success_move_this_run' => 0,
            'success_db_update_this_run' => 0,
            'failed_move_this_run' => 0,
            'failed_db_update_this_run' => 0,
            'skipped_this_run' => 0,
        ], now()->addMinutes(30)); // Cache berlaku 30 menit

        // Jika inbox kosong, langsung keluar
        if ($totalFilesInInbox === 0) {
            $this->info('Inbox kosong. Tidak ada yang diproses.');
             Cache::put('photo_processing_status.running', false); // Set status tidak berjalan
            return 0; // Exit code 0 menandakan sukses
        }

        // 2. Proses file satu per satu, dibatasi oleh --limit
        foreach (array_slice($files, 0, $limit) as $filePathRelative) {
            $totalProcessed++;
            $filename = basename($filePathRelative);
            $sourcePath = $filePathRelative; // Path relatif sumber di disk 'local'

            // 3. Parse nama file (format: objectid_idpel_suffix.ext)
            $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $parts = explode('_', $filenameWithoutExt);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            $objectId = null;
            $idpel = null;
            $fullSuffix = null;
            $dbColumn = null;

            // Perlu minimal 3 bagian dan objectid harus angka
            if (count($parts) >= 3 && is_numeric($parts[0])) {
                $objectId = $parts[0];
                $idpel = $parts[1];
                $suffixParts = array_slice($parts, 2);
                $fullSuffix = strtolower(implode('_', $suffixParts));

                // Tentukan kolom DB target
                if ($fullSuffix === 'foto_app') {
                    $dbColumn = 'foto_kwh';
                } elseif ($fullSuffix === 'foto_persil') {
                    $dbColumn = 'foto_bangunan';
                } else {
                    Log::warning("Suffix tidak dikenali: '{$fullSuffix}' dari file {$filename}");
                }
            } else {
                Log::warning("Format nama file dasar (objectid/jumlah bagian) tidak sesuai: {$filename}");
           }

            // Lanjutkan hanya jika parsing berhasil dan kolom DB target valid
            if ($dbColumn && $objectId && $idpel && $extension) {

                // Bangun path tujuan fisik (di disk 'public')
                $destinationDir = $this->destinationBase . '/' . $idpel; 
                $destinationPathPhysical = $destinationDir . '/' . $filename; 
                $destinationPathDb = $destinationDir . '/' . $filename; 

                $mappingRecord = null; // Inisialisasi

                // >>> LOGIKA PERBAIKAN OVERWRITE DENGAN EKSTENSI BERBEDA <<<
                $baseNameWithoutExt = $objectId . '_' . $idpel . '_' . $fullSuffix; 
                
                // 1. Dapatkan daftar file yang ada di folder tujuan (hanya file, bukan direktori)
                $oldFiles = $diskPublic->files($destinationDir); 

                foreach ($oldFiles as $oldFilePath) {
                    $oldFilenameInStorage = pathinfo($oldFilePath, PATHINFO_FILENAME);
                    
                    // Cek apakah nama file yang sudah ada di folder tujuan DIMULAI dengan pola nama dasar
                    if (str_starts_with($oldFilenameInStorage, $baseNameWithoutExt)) {
                        
                        // Periksa Izin Penghapusan secara eksplisit
                        try {
                            $diskPublic->delete($oldFilePath); 
                            $this->line("<fg=yellow>Overwrite:</> {$oldFilePath} dihapus.");
                            Log::info("Foto lama dihapus: {$oldFilePath} (Overwrite ID: {$objectId}).");
                        } catch (\Exception $e) {
                            Log::error("GAGAL HAPUS file lama {$oldFilePath} di storage: " . $e->getMessage());
                            $this->error("GAGAL HAPUS FILE LAMA: Cek izin file system untuk {$oldFilePath}");
                        }
                    }
                }

                try {
                    // --- Langkah 1: Pindahkan File Fisik ---
                    $diskPublic->makeDirectory($destinationDir); 
                    // Pindahkan dari disk 'local' ke disk 'public'
                    // Hati-hati: Storage::move memerlukan path relatif storage/app/
                    if (!Storage::move($sourcePath, 'public/' . $destinationPathPhysical)) {
                        throw new \Exception("Storage::move gagal memindahkan file.");
                    }
                    $totalSuccessMove++;
                    $this->line("<fg=blue>Dipindah:</> {$filename} -> {$destinationPathPhysical}");
                    Log::debug("Foto dipindahkan: {$filename} -> {$destinationPathPhysical}");

                    // --- Langkah 2: Update Database ---
                    try {
                        $mappingRecord = TemporaryMapping::where('objectid', $objectId)->first();

                        if ($mappingRecord) {
                            $mappingRecord->{$dbColumn} = $destinationPathDb;
                            $mappingRecord->save();
                            $totalSuccessDbUpdate++;
                            $this->line("<fg=green>DB Update:</> objectid {$objectId}, {$dbColumn} = {$destinationPathDb}");
                            Log::debug("DB Update Success: objectid {$objectId}, set {$dbColumn} to {$destinationPathDb}");

                            // Atur izin file setelah berhasil dipindah DAN DB diupdate
                            $absolutePath = Storage::disk('public')->path($destinationPathPhysical); // Gunakan disk('public')
                            if (File::exists($absolutePath)) {
                                chmod($absolutePath, 0644); // Atur izin baca untuk web server
                                Log::debug("Izin file diatur ke 0644 untuk: {$destinationPathPhysical}");
                            }

                        } else {
                            $totalFailedDbUpdate++;
                            Log::warning("DB Update Gagal: TemporaryMapping objectid {$objectId} tidak ditemukan untuk file {$filename}. File sudah dipindah.");
                            $this->error("DB Update Gagal: Record objectid {$objectId} tidak ditemukan.");
                            // Pertimbangkan: Pindahkan file ini ke folder 'db_not_found' agar tidak diproses ulang?
                            // try { Storage::disk('public')->move($destinationPathPhysical, $this->inboxPath . '/db_not_found/' . $filename); } catch (\Exception $e) {}
                        }
                    } catch (\Exception $dbError) {
                        $totalFailedDbUpdate++;
                        Log::error("DB Update Error untuk objectid {$objectId} (File: {$filename}): " . $dbError->getMessage());
                        $this->error("DB Update Error: objectid {$objectId} - " . $dbError.getMessage());
                        // Pertimbangkan: Rollback pemindahan file jika DB gagal?
                        // try { Storage::disk('public')->move($destinationPathPhysical, $sourcePath); } catch (\Exception $e) {}
                    }
                    // --- Akhir Update Database ---

                } catch (\Exception $moveError) {
                    $totalFailedMove++;
                    Log::error("Gagal pindah foto {$filename} dari {$sourcePath}: " . $moveError->getMessage());
                    $this->error("Gagal pindah foto {$filename}: " . $moveError.getMessage());
                    // File masih ada di inbox, akan dicoba lagi nanti
                }
            } else {
                // Parsing nama file gagal atau suffix tidak dikenali
                $totalSkipped++;
                Log::warning("Format nama file foto tidak sesuai atau suffix tidak dikenali, dilewati: {$filename}");
                $this->warn("Dilewati (Format/Suffix Salah): {$filename}");
                // Pertimbangkan: Pindahkan file ini ke folder 'skipped' agar tidak diproses ulang?
                // try { Storage::move($sourcePath, $this->inboxPath . '/skipped/' . $filename); } catch (\Exception $e) {}
            }

            // Update progress di cache (pastikan key cache unik dan benar)
            $cacheKeyPrefix = 'photo_processing_status.';
            Cache::increment($cacheKeyPrefix.'processed_this_run');
            if ($totalSuccessMove > Cache::get($cacheKeyPrefix.'success_move_this_run', 0)) Cache::put($cacheKeyPrefix.'success_move_this_run', $totalSuccessMove);
            if ($totalSuccessDbUpdate > Cache::get($cacheKeyPrefix.'success_db_update_this_run', 0)) Cache::put($cacheKeyPrefix.'success_db_update_this_run', $totalSuccessDbUpdate);
            if ($totalFailedMove > Cache::get($cacheKeyPrefix.'failed_move_this_run', 0)) Cache::put($cacheKeyPrefix.'failed_move_this_run', $totalFailedMove);
            if ($totalFailedDbUpdate > Cache::get($cacheKeyPrefix.'failed_db_update_this_run', 0)) Cache::put($cacheKeyPrefix.'failed_db_update_this_run', $totalFailedDbUpdate);
            if ($totalSkipped > Cache::get($cacheKeyPrefix.'skipped_this_run', 0)) Cache::put($cacheKeyPrefix.'skipped_this_run', $totalSkipped);

        } // Akhir loop foreach

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        // Hitung ulang sisa file di inbox SETELAH pemrosesan
        $remainingFilesInInbox = count(Storage::files($this->inboxPath)); 

        // Tampilkan Rekap Sesi Ini
        $this->info("\n--- Rekap Pemrosesan Foto Sesi Ini ---");
        $this->info("Waktu: {$duration} detik");
        $this->info("Diproses: {$totalProcessed} (Limit: {$limit})");
        $this->line("<fg=blue>Sukses Pindah File:</> {$totalSuccessMove}");
        $this->line("<fg=green>Sukses Update DB:</> {$totalSuccessDbUpdate}");
        $this->line("<fg=red>Gagal Pindah File:</> {$totalFailedMove}");
        $this->line("<fg=red>Gagal Update DB:</> {$totalFailedDbUpdate}");
        $this->line("<fg=yellow>Dilewati (Format Salah):</> {$totalSkipped}");
        $this->info("Sisa di Inbox: {$remainingFilesInInbox}");

        // Update status akhir di cache
        Cache::put('photo_processing_status.running', $remainingFilesInInbox > 0); // Masih running jika ada sisa
        Cache::put('photo_processing_status.total_inbox', $remainingFilesInInbox); // Update jumlah sisa

        $this->info("Pemrosesan selesai.");
        return 0; // Exit code 0 menandakan sukses
    }
}