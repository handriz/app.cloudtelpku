<?php

namespace App\Jobs;

use App\Imports\MasterDataPelangganImport;
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

class ProcessPelangganImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;
    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, int $userId)
    {
         $this->filePath = $filePath;
         $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Temukan user yang melakukan upload
        $user = User::find($this->userId);
        
        if ($user) {
            $user->notify(new ImportStartedNotification());
        }

        // Jalankan proses import menggunakan class Importer Anda
        Excel::import(new MasterDataPelangganImport, $this->filePath);

        // Hapus file dari storage setelah proses import selesai
        Storage::delete($this->filePath);

        if ($user) {
           $user->notify(new ImportFinishedNotification());
        }
    }
}
