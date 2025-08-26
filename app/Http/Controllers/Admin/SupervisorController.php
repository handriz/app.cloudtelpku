<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Supervisor\Supervisor; // Import Supervisor client library
use Supervisor\Connector\XmlRpc; // Import XML-RPC connector
use Illuminate\Support\Facades\Log; // Untuk logging
use Illuminate\Support\Facades\Artisan; // Untuk memanggil Artisan Command

class SupervisorController extends Controller
{
    protected $supervisor;

    public function __construct()
    {
        // Dapatkan konfigurasi Supervisor dari .env
        $host = env('SUPERVISOR_HOST', '127.0.0.1');
        $port = env('SUPERVISOR_PORT', '9001');
        $username = env('SUPERVISOR_USERNAME', 'supervisor_user');
        $password = env('SUPERVISOR_PASSWORD', 'your_strong_password');

        try {
            // Inisialisasi konektor dan instance Supervisor untuk PEMANTAUAN status
            // Ini akan mencoba terhubung saat controller dibuat
            $connector = new XmlRpc(sprintf('http://%s:%s/RPC2', $host, $port), $username, $password);
            $this->supervisor = new Supervisor($connector);
        } catch (\Exception $e) {
            Log::error('Gagal menginisialisasi Supervisor koneksi (untuk pemantauan): ' . $e->getMessage());
            $this->supervisor = null; // Set null jika gagal inisialisasi
        }
    }

    /**
     * Menampilkan status queue workers.
     */
    public function index()
    {
        // Variabel untuk status koneksi Supervisor
        $pingSuccess = false;
        $processes = [];
        $error = null;

        if (!$this->supervisor) {
            $error = 'Gagal terhubung ke Supervisor. Pastikan Supervisor berjalan dan konfigurasinya benar di file .env dan konfigurasi Supervisor.';
        } else {
            try {
                // Coba untuk mendapatkan status Supervisor itu sendiri (running/stopped)
                $pingSuccess = $this->supervisor->getState()['statecode'] === 1; // 1 = RUNNING
                
                if ($pingSuccess) {
                    // Jika Supervisor berjalan, ambil informasi semua proses
                    $allProcesses = $this->supervisor->getAllProcessInfo();
                    // Filter hanya proses worker Laravel Anda
                    $processes = collect($allProcesses)->filter(function($process) {
                        return str_starts_with($process['group'], 'laravel-worker'); // Sesuaikan nama grup Supervisor Anda
                    })->all();
                } else {
                    $error = 'Supervisor tidak berjalan (status: ' . $this->supervisor->getState()['statename'] . ').';
                }
            } catch (\Exception $e) {
                Log::error('Error saat mengambil status Supervisor: ' . $e->getMessage());
                $error = 'Terjadi kesalahan saat mengambil status worker: ' . $e->getMessage();
            }
        }

        return view('admin.supervisor.index', compact('processes', 'pingSuccess', 'error'));
    }

    /**
     * Mengubah status (start/stop/restart) worker.
     * Ini mengirim Artisan Command ke queue.
     */
    public function updateProcess(Request $request)
    {
        // Pastikan hanya permintaan AJAX yang diterima
        if (!$request->ajax()) {
            return response()->json(['error' => 'Permintaan tidak valid.'], 400);
        }

        $request->validate([
            'process_name' => 'required|string',
            'action' => 'required|in:start,stop,restart',
        ]);

        $processName = $request->input('process_name');
        $action = $request->input('action');
        
        try {
            // Memanggil Artisan Command secara synchronous untuk demo atau kasus tertentu
            // Untuk produksi yang lebih aman dan non-blocking,
            // Anda akan MENGIRIM JOB yang membungkus pemanggilan Artisan ini ke QUEUE
            // Contoh: RunSupervisorCommand::dispatch($action, $processName);
            // Maka pastikan Anda sudah membuat Job tersebut dan mengimpornya.

            // Untuk demonstrasi dan agar response langsung terlihat:
            Artisan::call('supervisor:control', [
                'action' => $action,
                'process_name' => $processName
            ]);
            $commandOutput = Artisan::output(); // Dapatkan output dari command Artisan

            Log::info("Aksi Supervisor '{$action}' berhasil dikirim untuk proses '{$processName}'. Output Artisan: " . $commandOutput);
            
            // Beri sedikit jeda agar Supervisor dan API-nya punya waktu untuk mengupdate status
            sleep(2); 

            // Setelah aksi, panggil ulang status untuk memastikan UI terupdate
            $newStatus = $this->getProcessStatus($processName);
            return response()->json(['success' => "Perintah '{$action}' untuk proses '{$processName}' berhasil dieksekusi. Output: " . $commandOutput, 'status' => $newStatus]);
        } catch (\Exception $e) {
            Log::error("Error saat mengirim aksi Supervisor '{$action}' untuk proses '{$processName}': " . $e->getMessage());
            return response()->json(['error' => "Terjadi kesalahan: " . $e->getMessage(), 'status' => $this->getProcessStatus($processName)], 500);
        }
    }

    /**
     * Mendapatkan status proses tunggal (digunakan setelah aksi untuk update UI).
     */
    protected function getProcessStatus(string $processName)
    {
        if (!$this->supervisor) {
            return 'UNKNOWN_NO_CONNECTION'; // Tidak ada koneksi Supervisor
        }
        try {
            $info = $this->supervisor->getProcessInfo($processName);
            return $info['statename']; // RUNNING, STOPPED, STARTING, FATAL, dll.
        } catch (\Exception $e) {
            Log::error("Gagal mendapatkan status proses '{$processName}' setelah aksi: " . $e->getMessage());
            return 'UNKNOWN_API_ERROR'; // Terjadi error saat memanggil API
        }
    }
}
