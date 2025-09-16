<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class QueueMonitorController extends Controller
{
    public function index(Request $request)
    {
        $pendingJobs = DB::table('jobs')->get();
        $failedJobs = DB::table('failed_jobs')->get();

        // Decode payload JSON agar bisa dibaca
        $failedJobs->each(function ($job) {
            $payload = json_decode($job->payload);
            // Ambil nama class dari job
            $job->displayName = $payload->displayName ?? 'Unknown Job';
        });

        $lastHeartbeat = Cache::get('scheduler_last_heartbeat');
        $isWorkerActive = false;
        if ($lastHeartbeat && $lastHeartbeat->diffInMinutes(now()) < 2) {
            // Jika detak jantung tercatat dalam 2 menit terakhir, anggap aktif.
            $isWorkerActive = true;
        }

        $viewData = compact('pendingJobs', 'failedJobs', 'isWorkerActive', 'lastHeartbeat');

        if ($request->has('is_ajax')) {
            return view('admin.queue.partials.monitor_content', $viewData);
        }
        
        return view('admin.queue.monitor', $viewData);
    }

    public function retry($id)
    {
        // Jalankan perintah artisan untuk retry job
        Artisan::call('queue:retry', ['id' => [$id]]);
        
        return response()->json(['message' => 'Pekerjaan telah dikirim kembali ke antrian.']);
    }

    public function delete($id)
    {
        // Hapus job dari tabel failed_jobs
        DB::table('failed_jobs')->where('id', $id)->delete();
        
        return response()->json(['message' => 'Pekerjaan yang gagal telah dihapus.']);
    }
}