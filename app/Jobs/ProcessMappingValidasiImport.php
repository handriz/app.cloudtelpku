<?php

namespace App\Jobs;

use App\Imports\MappingValidasiImport;
use App\Models\User;
use App\Models\TemporaryMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Notifications\ImportFinishedNotification;
use App\Notifications\ImportStartedNotification;
use App\Notifications\ImportHeaderValidationFailedNotification; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessMappingValidasiImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;

    private $sourceFolder = 'uploads_sementara_mapping/';
    private $destinationFolder = 'public/mapping_photos/';

    public function __construct(string $filePath, int $userId)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        Log::info("--- JOB PROCESS MAPPING VALIDASI IMPORT DIMULAI (User: {$this->userId}) ---");
        $user = User::find($this->userId);
        $absolutePath = storage_path('app/' . $this->filePath);

        try {
            if ($user) {
                $user->notify(new ImportStartedNotification());
            }

            // =================================================================
            // LANGKAH 1: SINKRONISASI DI AWAL (PRE-SYNC)
            // =================================================================
            Log::info("Memulai Pre-Scan ObjectID Sequence untuk file: {$this->filePath}...");
            
            // 1. Dapatkan Max ID saat ini (dari sequence)
            $currentSequenceMax = DB::table('objectid_sequence')->max('id') ?? 0;
            
            // 2. Buka file CSV untuk dibaca (Pass 1)
            $fileHandle = fopen($absolutePath, 'r');
            if ($fileHandle === false) {
                throw new \Exception("Gagal membuka file CSV untuk Pre-Sync.");
            }

            // Asumsi delimiter adalah ';' (titik koma)
            $header = fgetcsv($fileHandle, 0, ';'); 
            if ($header === false) {
                 throw new \Exception("Gagal membaca header file CSV. Pastikan file tidak kosong.");
            }
            
            $objectidColumnIndex = array_search('objectid', $header); // Cari kolom 'objectid'

            if ($objectidColumnIndex === false) {
                // Jika kolom 'objectid' tidak ada, kita tidak bisa sinkronisasi
                Log::warning("Kolom 'objectid' tidak ditemukan di header CSV. Sinkronisasi dibatalkan.");
                fclose($fileHandle);

            } else {
                // Jika kolom 'objectid' ditemukan, cari MAX
                $maxInFile = 0;
                while (($row = fgetcsv($fileHandle, 0, ';')) !== false) {
                    if (isset($row[$objectidColumnIndex])) {
                        $objectId = (int) $row[$objectidColumnIndex];
                        if ($objectId > $maxInFile) {
                            $maxInFile = $objectId;
                        }
                    }
                }
                fclose($fileHandle);
                
                Log::info("Pre-Sync: Max ObjectID di file = $maxInFile. Max di Sequence = $currentSequenceMax.");

                // 3. Hanya sinkronisasi JIKA Max di File > Max di Sequence
                if ($maxInFile > $currentSequenceMax) {
                    $nextValue = $maxInFile + 1;
                    // Atur AUTO_INCREMENT berikutnya
                    DB::statement("ALTER TABLE objectid_sequence AUTO_INCREMENT = ?", [$nextValue]);
                    Log::info("Sinkronisasi sequence objectid berhasil. AUTO_INCREMENT diatur ke: " . $nextValue);
                } else {
                    Log::info("Sinkronisasi tidak diperlukan (Max di file lebih rendah atau sama).");
                }
            }
            // =================================================================
            // AKHIR PRE-SYNC
            // =================================================================

            // LANGKAH 2: JALANKAN IMPORT (Pass 2)
            Log::info("Memulai import data massal (Maatwebsite)...");
            $import = new MappingValidasiImport();
            Excel::import($import, $absolutePath); // Ini sekarang aman
            
            // Hapus file
            Storage::delete($this->filePath);

            if ($user) {
                $user->notify(new ImportFinishedNotification());
            }

        } catch (\Exception $e) {
            // Blok ini akan menangkap semua error
            
            // Hapus file yang gagal diproses.
            Storage::delete($this->filePath);
            
            if ($user) {
                $user->notify(new ImportHeaderValidationFailedNotification('Import gagal: ' . $e->getMessage()));
            }

            $this->fail($e);
        }
    }
}

