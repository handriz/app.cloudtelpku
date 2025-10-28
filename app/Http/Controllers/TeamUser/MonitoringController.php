<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitoringController extends Controller
{
    public function photoProcessingStatus()
    {
        // Ambil data status dari cache
        $status = Cache::get('photo_processing_status', [
            'running' => false,
            'last_run' => 'Belum pernah berjalan',
            'total_inbox' => 0,
            'processed_this_run' => 0,
            'success_this_run' => 0,
            'failed_this_run' => 0,
            'skipped_this_run' => 0,
        ]);

        // Kirim data ke view
        return view('team.monitoring.photo_status', compact('status'));
    }
}
