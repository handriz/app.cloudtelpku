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
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Rute halaman depan
Route::get('/', function () {
    // return view('welcome');
    return redirect()->route('login');
});

// Grup middleware untuk pengguna yang sudah terautentikasi dan terverifikasi email
Route::middleware(['auth', 'verified'])->group(function () {

    // Rute /dashboard umum yang akan mengarahkan pengguna ke dashboard sesuai perannya
    Route::get('/dashboard', function () {
    $user = Auth::user();

    // Arahkan ke dashboard spesifik berdasarkan peran pengguna
    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    } elseif ($user->hasRole('tl_user')) {
        return redirect()->route('tl_user.dashboard');
    } elseif ($user->hasRole('app_user')) {
        return redirect()->route('app_user.dashboard');
    } elseif ($user->hasRole('executive_user')) {
        return redirect()->route('executive.dashboard');
    }
    // Pengguna tidak memiliki peran khusus, arahkan ke dashboard default
        return view('dashboard');
        
    })->name('dashboard');

    // ======================================================================
    // RUTE UNTUK MANAJEMEN PENGGUNA
    // Dapat diakses oleh peran manapun yang memiliki izin 'manage-users'
    // ======================================================================
    Route::prefix('manajemen-pengguna')->name('manajemen-pengguna.')->middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class);
    });

    // ======================================================================
    // RUTE UNTUK PANEL ADMIN
    // Dilindungi oleh middleware 'can:access-admin-dashboard' (diperiksa oleh Gate dinamis)
    // ======================================================================
    Route::prefix('admin')->name('admin.')->middleware('can:access-admin-dashboard')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('menu', MenuController::class);
        Route::resource('hierarchies', HierarchyController::class)->except(['show']);
        Route::resource('permissions', PermissionController::class)->except(['show']);
        Route::post('/permissions/update-role-permissions', [PermissionController::class, 'updateRolePermissions'])->name('permissions.updateRolePermissions');
    
        // Manajemen Data
        Route::prefix('manajemen-data')->name('manajemen_data.')->group(function () {
            Route::get('dashboard', [MasterDataController::class, 'dashboard'])->name('dashboard');
            Route::resource('pelanggan', MasterDataController::class)->except(['show']);
            Route::get('pelanggan/upload', [MasterDataController::class, 'uploadForm'])->name('upload.form');  
        // --- Rute Khusus untuk Chunking Upload ---
            Route::post('pelanggan/upload-chunk', [MasterDataController::class, 'uploadChunk'])->name('upload.chunk');
            Route::post('pelanggan/merge-chunks', [MasterDataController::class, 'mergeChunks'])->name('merge.chunks');   
        // Edit, Update dan Delete Data Pelanggan Individual
            Route::get('pelanggan/{pelanggan}/edit', [MasterDataController::class, 'edit'])->name('edit');
            Route::put('pelanggan/{pelanggan}', [MasterDataController::class, 'update'])->name('update');
            Route::delete('pelanggan/{pelanggan}', [MasterDataController::class, 'destroy'])->name('destroy');
        });

        // --- Rute untuk Manajemen Supervisor (Queue Workers) ---
        Route::prefix('supervisor')->name('supervisor.')->group(function () {
            Route::get('workers', [SupervisorController::class, 'index'])->name('index');
            // Rute ini akan mengirimkan Artisan Command ke queue
            Route::post('update-process', [SupervisorController::class, 'updateProcess']);
        });
    
    });

    // ======================================================================
    // RUTE UNTUK PANEL PENGGUNA APLIKASI (TL User)
    // ======================================================================
    Route::prefix('tluser')->name('tl_user.')->middleware('can:access-tl_user-dashboard')->group(function () {
        Route::get('/dashboard', [TlUserDashboardController::class, 'index'])->name('dashboard');
        // Tambahkan rute khusus untuk pengguna aplikasi di sini
        // Contoh: Route::get('/reports', [AppUserReportController::class, 'index'])->name('reports');
    });

        // ======================================================================
    // RUTE UNTUK PANEL PENGGUNA APLIKASI (App User)
    // ======================================================================
    Route::prefix('appuser')->name('app_user.')->middleware('can:access-app_user-dashboard')->group(function () {
        Route::get('/dashboard', [AppUserDashboardController::class, 'index'])->name('dashboard');
        // Tambahkan rute khusus untuk pengguna aplikasi di sini
        // Contoh: Route::get('/reports', [AppUserReportController::class, 'index'])->name('reports');
    });

    // ======================================================================
    // RUTE UNTUK PANEL EKSEKUTIF (Executive User)
    // ======================================================================
    Route::prefix('executive')->name('executive.')->middleware('can:access-executive-dashboard')->group(function () {
        Route::get('/dashboard', [ExecutiveDashboardController::class, 'index'])->name('dashboard');
        // Tambahkan rute khusus untuk pengguna eksekutif di sini
        // Contoh: Route::get('/analytics', [ExecutiveAnalyticsController::class, 'index'])->name('analytics');
    });

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php'; //