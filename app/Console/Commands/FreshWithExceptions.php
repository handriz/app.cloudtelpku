<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class FreshWithExceptions extends Command
{
    protected $signature = 'migrate:fresh-except {tables} {--seed : Menjalankan database seeder}';
    protected $description = 'Menjalankan migrate:fresh --seed, dengan mengecualikan tabel yang ditentukan.';

    public function handle()
    {
        $excludedTables = explode(',', $this->argument('tables'));
        $tempTableNames = [];

        foreach ($excludedTables as $table) {
            $table = trim($table);
            if (Schema::hasTable($table)) {
                $tempName = $table . '_temp_migration_' . now()->timestamp;
                $this->info("Menyimpan tabel: $table -> $tempName");
                Schema::rename($table, $tempName);
                $tempTableNames[$table] = $tempName;
            } else {
                $this->warn("Tabel '$table' tidak ditemukan, dilewati.");
            }
        }

        $this->info('Menghapus semua tabel lain...');
        Schema::disableForeignKeyConstraints();
        
        $tables = Schema::getAllTables();
        foreach ($tables as $table) {
            $tableName = $table->{'Tables_in_' . config('database.connections.mysql.database')};
            // Hapus semua tabel KECUALI tabel sementara yang kita simpan
            if (!in_array($tableName, $tempTableNames)) {
                Schema::drop($tableName);
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->info('Selesai menghapus tabel.');

        $this->info('Menjalankan `php artisan migrate`...');
        Artisan::call('migrate');
        $this->info(Artisan::output());

        if ($this->option('seed')) {
            $this->info('Menjalankan `php artisan db:seed`...');
            Artisan::call('db:seed');
            $this->info(Artisan::output());
        }

        foreach ($tempTableNames as $originalName => $tempName) {
            $this->info("Menghapus tabel '$originalName' baru yang kosong (jika ada).");
            Schema::dropIfExists($originalName);

            $this->info("Mengembalikan tabel asli: $tempName -> $originalName");
            Schema::rename($tempName, $originalName);
        }

        $this->info('Perintah `migrate:fresh-except` berhasil diselesaikan! 🚀');
        return 0;
    }
}