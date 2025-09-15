<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Imports\MasterDataPelangganImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ProcessPelangganImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath)
    {
         $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Jalankan proses import menggunakan class Importer Anda
        Excel::import(new MasterDataPelangganImport, $this->filePath);

        // Hapus file dari storage setelah proses import selesai
        Storage::delete($this->filePath);
    }
}
