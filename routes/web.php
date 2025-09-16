<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AppUser\DashboardController as AppUserDashboardController;
use App\Http\Controllers\TlUser\DashboardController as TlUserDashboardController; 
use App\Http\Controllers\Executive\DashboardController as ExecutiveDashboardController; 
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\HierarchyController; 
use App\Http\Controllers\Admin\SupervisorController; 
use App\Http\Controllers\Admin\MasterDataController; 


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Semua rute web akan dimuat di sini.
|
*/

// Rute halaman depan, langsung mengarahkan ke halaman login
Route::get('/', function () {
    return redirect()->route('login');
});

// Grup rute untuk pengguna yang sudah terautentikasi dan terverifikasi email
Route::middleware(['auth', 'verified'])->group(function () {

    // Rute dashboard umum yang akan mengarahkan pengguna ke dashboard sesuai perannya
    Route::get('/dashboard', function () {
        $user = Auth::user();

        // Menggunakan array untuk pemetaan yang lebih rapi dan mudah diubah
        $dashboardRoutes = [
            'admin'          => 'admin.dashboard',
            'tl_user'        => 'tl_user.dashboard',
            'app_user'       => 'app_user.dashboard',
            'executive_user' => 'executive.dashboard',
        ];
        // Temukan peran pengguna dan arahkan ke rute yang sesuai
        foreach ($dashboardRoutes as $roleName => $routeName) {
            if ($user->hasRole($roleName)) {
                return redirect()->route($routeName);
            }
        }
        // Pengguna tidak memiliki peran khusus, arahkan ke dashboard default
        return view('dashboard');  
    })->name('dashboard');

    // ======================================================================
    // RUTE UNTUK MANAJEMEN PENGGUNA
    // Dilindungi oleh middleware 'can' untuk izin 'manage-users'
    // ======================================================================
    Route::prefix('manajemen-pengguna')->name('manajemen-pengguna.')->middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class);
    });

    // ======================================================================
    // RUTE UNTUK PANEL ADMIN
    // Dilindungi oleh middleware 'can:access-admin-dashboard' (diperiksa oleh Gate dinamis)
    // ======================================================================
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::prefix('queue-monitor')->name('queue.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\QueueMonitorController::class, 'index'])->name('monitor');
            Route::post('retry/{id}', [App\Http\Controllers\Admin\QueueMonitorController::class, 'retry'])->name('retry');
            Route::post('delete/{id}', [App\Http\Controllers\Admin\QueueMonitorController::class, 'delete'])->name('delete');
        });
        // Resource routes untuk manajemen menu, hierarki, dan permission
        Route::resource('menu', MenuController::class);
        Route::resource('hierarchies', HierarchyController::class)->except(['show']);
        Route::resource('permissions', PermissionController::class)->except(['show']);

        // Rute khusus di dalam group admin
        Route::post('/permissions/update-role-permissions', [PermissionController::class, 'updateRolePermissions'])->name('permissions.updateRolePermissions');
    
        // Manajemen Data
        Route::prefix('manajemen-data')->name('manajemen_data.')->group(function () {
            Route::get('dashboard', [MasterDataController::class, 'dashboard'])->name('dashboard');
                
            // Route::resource otomatis membuat route edit, update, dan destroy
            Route::resource('pelanggan', MasterDataController::class)->except(['show']);

            // Rute khusus yang tidak termasuk resource
            Route::get('upload', [MasterDataController::class, 'uploadForm'])->name('upload');  
            Route::post('upload-chunk', [MasterDataController::class, 'uploadChunk'])->name('upload.chunk');
            Route::post('merge-chunks', [MasterDataController::class, 'mergeChunks'])->name('merge.chunks');   

        });

        // Manajemen Supervisor (Queue Workers)
        Route::prefix('supervisor')->name('supervisor.')->group(function () {
                Route::get('workers', [SupervisorController::class, 'index'])->name('index');
                Route::post('update-process', [SupervisorController::class, 'updateProcess']);
            });
    
    });

    // ======================================================================
    // RUTE UNTUK PANEL PENGGUNA APLIKASI (TL & App User)
    // Menggunakan middleware 'role' yang spesifik
    // ======================================================================
    Route::prefix('tluser')->name('tl_user.')->middleware('role:tl_user')->group(function () {
        Route::get('/dashboard', [TlUserDashboardController::class, 'index'])->name('dashboard');
    });

    Route::prefix('appuser')->name('app_user.')->middleware('role:app_user')->group(function () {
        Route::get('/dashboard', [AppUserDashboardController::class, 'index'])->name('dashboard');
    });

    // ======================================================================
    // RUTE UNTUK PANEL EKSEKUTIF (Executive User)
    // Menggunakan middleware 'role' yang spesifik
    // ======================================================================
    Route::prefix('executive')->name('executive.')->middleware('role:executive_user')->group(function () {
        Route::get('/dashboard', [ExecutiveDashboardController::class, 'index'])->name('dashboard');
    });


});

// Rute untuk manajemen profil (dari Laravel Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php'; //