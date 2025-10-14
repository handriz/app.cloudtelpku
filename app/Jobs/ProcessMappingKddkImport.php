<?php

namespace App\Jobs;

use App\Imports\MappingKddkImport;
use App\Models\User;
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

class ProcessMappingKddkImport implements ShouldQueue
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
        $user = User::find($this->userId);

        try {
            // Buat path absolut ke file yang akan dibaca oleh library Excel.
            // Ini adalah baris yang diperbaiki.
            $absolutePath = storage_path('app/' . $this->filePath);

            // Kirim notifikasi "Proses Dimulai"
            if ($user) {
                $user->notify(new ImportStartedNotification());
            }

            // Buat instance importer dan langsung jalankan proses import.
            // Gunakan path absolut untuk membaca file.
            $import = new MappingKddkImport();
            Excel::import($import, $absolutePath);
                        
            // Hapus file setelah proses import selesai.
            // Storage::delete() menggunakan path relatif dari disk 'storage/app'.
            Storage::delete($this->filePath);

            // Kirim notifikasi "Proses Selesai"
            if ($user) {
                $user->notify(new ImportFinishedNotification());
            }

        } catch (\Exception $e) {
            // Blok ini akan menangkap semua error, termasuk jika ada kolom yang hilang,
            // atau error database saat proses import
            
            // Hapus file yang gagal diproses.
            Storage::delete($this->filePath);
            
            if ($user) {
                $user->notify(new ImportHeaderValidationFailedNotification('Import gagal: ' . $e->getMessage()));
            }

            $this->fail($e);
        }
    }
}

