<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanTemporaryPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-temporary-photos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghapus file foto sementara yang sudah lama di folder temp_photos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
       $this->info('Memulai pembersihan foto sementara...');

       $files = Storage::files('temp_photos');
       $deletedCount = 0;

       foreach ($files as $file) {
            // Hapus file yang lebih tua dari 24 jam
            if (Storage::lastModified($file) < Carbon::now()->subHours(24)->getTimestamp()) {
                Storage::delete($file);
                $deletedCount++;
            }
        }

        $this->info("Pembersihan selesai. {$deletedCount} file telah dihapus.");
        return 0;
    }
}
