<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

class AppDownloadController extends Controller
{
    public function index()
    {
        // 1. Ambil Data dari Settings
        $apkFilename = AppSetting::findValue('mobile_apk_filename');
        $version     = AppSetting::findValue('mobile_min_version', null, '1.0.0');
        $updateMsg   = AppSetting::findValue('mobile_update_message');
        $lastUpload  = AppSetting::findValue('mobile_apk_uploaded_at');

        // 2. Cek apakah file fisik benar-benar ada
        $fileExists = $apkFilename && Storage::disk('public')->exists('apk/' . $apkFilename);
        
        // 3. Generate Link URL
        $downloadUrl = $fileExists ? asset('storage/apk/' . $apkFilename) : '#';

        // 4. Hitung Ukuran File (Optional, untuk display)
        $fileSize = '0 MB';
        if ($fileExists) {
            $sizeBytes = Storage::disk('public')->size('apk/' . $apkFilename);
            $fileSize = round($sizeBytes / 1024 / 1024, 2) . ' MB';
        }

        return view('public.download_app', compact(
            'fileExists', 
            'downloadUrl', 
            'version', 
            'updateMsg', 
            'lastUpload',
            'fileSize'
        ));
    }
}
