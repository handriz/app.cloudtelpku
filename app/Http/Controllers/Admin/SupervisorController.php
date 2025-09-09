<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Supervisor\Supervisor; // Kelas Supervisor utama
use fXmlRpc\Client; // Client fXmlRpc
use fXmlRpc\Transport\HttpAdapterTransport; // Transport HTTP client
use Http\Adapter\Guzzle7\Client as GuzzleAdapter; // HTTP Client adapter (Guzzle)

// Ini adalah adapter yang kita butuhkan untuk menjembatani PSR-17 ke HTTPlug lama
use Http\Message\MessageFactory\Psr17MessageFactory; 

// Kita perlu implementasi PSR-17 Message Factory yang akan diadaptasi
use GuzzleHttp\Psr7\HttpFactory; 

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class SupervisorController extends Controller
{
    protected $supervisor;

    public function __construct()
    {
        $host = env('SUPERVISOR_HOST', '127.0.0.1');
        $port = env('SUPERVISOR_PORT', '9001');
        $username = env('SUPERVISOR_USERNAME', 'supervisor_user');
        $password = env('SUPERVISOR_PASSWORD', 'your_strong_password'); // Ganti dengan password yang benar!

        try {
            // 1. Inisialisasi HTTP Client (GuzzleAdapter)
            $httpClient = new GuzzleAdapter();

            // 2. Buat objek PSR-17 Message Factory (dari guzzlehttp/psr7)
            $psr17MessageFactory = new HttpFactory(); 

            // 3. Adaptasikan PSR-17 MessageFactory ke antarmuka Http\Message\MessageFactory yang diharapkan
            // Ini adalah kunci untuk mengatasi error Argument #1
            $adaptedMessageFactory = new Psr17MessageFactory($psr17MessageFactory); 

            // 4. Buat Transport dengan Message Factory (yang sudah diadaptasi) dan HTTP Client
            // URUTAN ARGUMENNYA ADALAH ($adaptedMessageFactory, $httpClient)
            $transport = new HttpAdapterTransport($adaptedMessageFactory, $httpClient);

            // 5. Buat instance fXmlRpc Client
            $client = new Client(sprintf('http://%s:%s/RPC2', $host, $port), $transport);
            $client->setAuthentication($username, $password); // Set otentikasi basic HTTP

            // 6. Inisialisasi Supervisor dengan client fXmlRpc ini
            $this->supervisor = new Supervisor($client);

        } catch (\Exception $e) {
            Log::error('Gagal menginisialisasi Supervisor koneksi (untuk pemantauan): ' . $e->getMessage());
            $this->supervisor = null;
        }
    }

    /**
     * Menampilkan status queue workers.
     */
    public function index()
    {
        $pingSuccess = false;
        $processes = [];
        $error = null;

        if (!$this->supervisor) {
            $error = 'Gagal terhubung ke Supervisor. Pastikan Supervisor berjalan dan konfigurasinya benar di file .env dan konfigurasi Supervisor.';
        } else {
            try {
                $pingSuccess = $this->supervisor->getState()['statecode'] === 1; // 1 = RUNNING
                
                if ($pingSuccess) {
                    $allProcesses = $this->supervisor->getAllProcessInfo();
                    $processes = collect($allProcesses)->filter(function($process) {
                        return str_starts_with($process['group'], 'laravel-worker'); // Sesuaikan nama grup Supervisor Anda
                    })->all();
                } else {
                    $error = 'Supervisor tidak berjalan (status: ' . $this->supervisor->getState()['statename'] . ').';
                }
            } catch (\Exception | \fXmlRpc\Exception\FaultException $e) { // Tangani juga FaultException dari fXmlRpc
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
            Artisan::call('supervisor:control', [
                'action' => $action,
                'process_name' => $processName
            ]);
            $commandOutput = Artisan::output(); 

            Log::info("Aksi Supervisor '{$action}' berhasil dikirim untuk proses '{$processName}'. Output Artisan: " . $commandOutput);
            
            sleep(2); 

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
            return 'UNKNOWN_NO_CONNECTION'; 
        }
        try {
            $info = $this->supervisor->getProcessInfo($processName);
            return $info['statename']; 
        } catch (\Exception | \fXmlRpc\Exception\FaultException $e) { 
            Log::error("Gagal mendapatkan status proses '{$processName}' setelah aksi: " . $e->getMessage());
            return 'UNKNOWN_API_ERROR'; 
        }
    }
}