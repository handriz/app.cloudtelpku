<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Supervisor\Supervisor;
use Supervisor\Connector\XmlRpc;
use Illuminate\Support\Facades\Log;

class SupervisorControl extends Command
{
    /**
     * Nama dan tanda tangan dari perintah konsol.
     * Kita menggunakan {process_name?} untuk proses spesifik, dan {action} untuk start/stop/restart.
     * @var string
     */
    protected $signature = 'supervisor:control {action} {process_name?}';

    /**
     * Deskripsi perintah konsol.
     * @var string
     */
    protected $description = 'Perform an action (start, stop, restart) on a specific Supervisor process, or all Laravel workers.';

    /**
     * Jalankan perintah konsol.
     * @return int
     */
    public function handle()
    {
        $action = $this->argument('action');
        $processName = $this->argument('process_name');

        // Dapatkan konfigurasi Supervisor dari .env
        $host = env('SUPERVISOR_HOST', '127.0.0.1');
        $port = env('SUPERVISOR_PORT', '9001');
        $username = env('SUPERVISOR_USERNAME', 'supervisor_user');
        $password = env('SUPERVISOR_PASSWORD', 'your_strong_password');

        try {
            // Inisialisasi konektor XML-RPC ke Supervisor
            $connector = new XmlRpc(sprintf('http://%s:%s/RPC2', $host, $port), $username, $password);
            $supervisor = new Supervisor($connector);

            // Validasi aksi
            if (!in_array($action, ['start', 'stop', 'restart'])) {
                $this->error("Aksi tidak valid: {$action}. Hanya 'start', 'stop', 'restart' yang diizinkan.");
                return Command::FAILURE;
            }

            // Jika process_name tidak diberikan, asumsikan aksi untuk semua worker Laravel
            if (empty($processName)) {
                $this->info("Melakukan aksi '{$action}' untuk semua worker Laravel...");
                $allProcesses = $supervisor->getAllProcessInfo();
                $laravelWorkers = collect($allProcesses)->filter(function($p) {
                    return str_starts_with($p['group'], 'laravel-worker'); // Sesuaikan nama grup Supervisor Anda
                });

                if ($laravelWorkers->isEmpty()) {
                    $this->error('Tidak ada worker Laravel ditemukan untuk aksi ini.');
                    return Command::FAILURE;
                }

                foreach ($laravelWorkers as $worker) {
                    $this->performAction($supervisor, $action, $worker['name']);
                }
                $this->info("Aksi '{$action}' untuk semua worker Laravel berhasil diselesaikan.");
            } else {
                // Lakukan aksi untuk proses spesifik
                $this->info("Melakukan aksi '{$action}' untuk proses: {$processName}");
                $this->performAction($supervisor, $action, $processName);
                $this->info("Aksi '{$action}' untuk proses {$processName} berhasil diselesaikan.");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Gagal melakukan aksi Supervisor: {$e->getMessage()}");
            Log::error("SupervisorControl Command gagal: " . $e->getMessage() . " di baris " . $e->getLine());
            return Command::FAILURE;
        }
    }

    /**
     * Fungsi helper untuk melakukan aksi start/stop/restart.
     * @param \Supervisor\Supervisor $supervisor
     * @param string $action
     * @param string $processName
     * @return void
     * @throws \Exception
     */
    protected function performAction(Supervisor $supervisor, string $action, string $processName)
    {
        $result = false;
        switch ($action) {
            case 'start':
                $result = $supervisor->startProcess($processName, false);
                break;
            case 'stop':
                $result = $supervisor->stopProcess($processName, false);
                break;
            case 'restart':
                // Untuk restart, hentikan dulu lalu mulai lagi
                $this->info("Menghentikan proses {$processName}...");
                $supervisor->stopProcess($processName, false);
                usleep(500000); // Jeda 0.5 detik
                $this->info("Memulai kembali proses {$processName}...");
                $result = $supervisor->startProcess($processName, false);
                break;
        }

        if (!$result) {
            throw new \Exception("Aksi '{$action}' gagal untuk proses '{$processName}'.");
        }
        Log::info("Supervisor: Aksi '{$action}' berhasil untuk proses '{$processName}' via Artisan command.");
    }
}
