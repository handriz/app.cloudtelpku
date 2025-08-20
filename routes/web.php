<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth; // Pastikan ini diimpor untuk Auth::user()
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AppUser\DashboardController as AppUserDashboardController; // Contoh jika Anda punya
use App\Http\Controllers\Executive\DashboardController as ExecutiveDashboardController; // Contoh jika Anda punya
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;

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

// Middleware grup untuk pengguna yang sudah terautentikasi dan terverifikasi email
// CATATAN: Verifikasi email (middleware('verified')) akan diperiksa SETELAH
// pengguna berhasil login DAN is_approved mereka TRUE.
// Jika email belum diverifikasi, mereka akan dialihkan ke /email/verify.
Route::middleware(['auth', 'verified'])->group(function () {

    // Rute /dashboard umum yang akan mengarahkan pengguna ke dashboard sesuai perannya
    Route::get('/dashboard', function () {
        $user = Auth::user();

        // Arahkan ke dashboard spesifik berdasarkan peran pengguna
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->role === 'app_user') {
            return redirect()->route('app_user.dashboard');
        } elseif ($user->role === 'executive_user') {
            return redirect()->route('executive.dashboard');
        }

        // Jika peran tidak cocok dengan yang didefinisikan, arahkan ke dashboard default atau error
        // Pastikan Anda memiliki view 'dashboard' ini atau ganti dengan rute yang aman
        return view('dashboard');
    })->name('dashboard');


    // ======================================================================
    // RUTE UNTUK PANEL ADMIN
    // Dilindungi oleh middleware 'can:access-admin-dashboard' (diperiksa oleh Gate dinamis)
    // ======================================================================
    Route::prefix('admin')->name('admin.')->group(function () {
        // Dashboard Admin
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Manajemen Pengguna (CRUD)
        // Rute ini mencakup: index, create, store, show, edit, update, destroy
        Route::resource('users', UserController::class);

        // Manajemen Item Menu (CRUD)
        // Rute ini mencakup: index, create, store, show, edit, update, destroy
        Route::resource('menu', MenuController::class);

        // Manajemen Izin Dinamis
        Route::resource('permissions', PermissionController::class)->except(['show']); // Contoh jika ingin CRUD Permission model
        // Rute untuk menampilkan matriks izin dan memproses pembaruannya
        Route::post('/permissions/update-role-permissions', [PermissionController::class, 'updateRolePermissions'])->name('permissions.updateRolePermissions');
    });

    // ======================================================================
    // RUTE UNTUK PANEL PENGGUNA APLIKASI (App User)
    // ======================================================================
    Route::prefix('app-user')->name('app_user.')->middleware('can:access-app_user-dashboard')->group(function () {
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
// Otentikasi Laravel Breeze/Jetstream routes
// Pastikan ini berada di luar grup middleware 'auth' utama jika Anda ingin
// halaman login/register dapat diakses oleh non-authenticated users.
require __DIR__.'/auth.php'; // Atau file auth bawaan Laravel lainnya