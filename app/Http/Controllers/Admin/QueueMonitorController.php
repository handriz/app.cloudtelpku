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

    $failedJobs->each(function ($job) {
        $payload = json_decode($job->payload);
        $job->displayName = $payload->displayName ?? 'Unknown Job';
    });

    $lastHeartbeat = Cache::get('scheduler_last_heartbeat');
    $isWorkerActive = ($lastHeartbeat && $lastHeartbeat->diffInMinutes(now()) < 2);
    
    // Ambil data status supervisor dari cache
    $supervisorStatus = Cache::get('supervisor_status', []);

    $viewData = compact('pendingJobs', 'failedJobs', 'isWorkerActive', 'lastHeartbeat', 'supervisorStatus');

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