<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\UserController;

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

Route::get('/', function () {
    // return view('welcome');
    return redirect()->route('login');
});

// Baris ini mengimpor semua rute terkait autentikasi (login, register, logout, verifikasi email, dll.)
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Grup Rute yang Dilindungi & Berbasis Peran
|--------------------------------------------------------------------------
|
| Semua rute di dalam grup ini akan memerlukan pengguna untuk:
| 1. Sudah terautentikasi (middleware 'auth').
| 2. Diarahkan ke dashboard yang sesuai berdasarkan peran mereka
|    (middleware 'role_redirect' kustom kita).
|
*/
Route::middleware(['auth', 'role_redirect'])->group(function () {
    // Rute Dashboard Default / Fallback
    // Ini adalah rute umum yang akan diakses pertama kali setelah login
    // dan akan ditangani oleh middleware 'role_redirect' untuk mengarahkan lebih lanjut.
    // Ini juga berfungsi sebagai dashboard jika peran pengguna tidak memiliki rute spesifik.

    Route::get('/dashboard', [DashboardController::class, 'defaultDashboard'])->name('dashboard');

    // Rute-rute Dashboard Spesifik Berdasarkan Peran
    // Meskipun middleware 'role_redirect' akan mengarahkan pengguna ke rute ini,
    // penting untuk mendefinisikan rute-rute ini secara eksplisit.
    Route::get('/admin/dashboard', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::get('/executive/dashboard', [DashboardController::class, 'executiveDashboard'])->name('executive.dashboard');
    Route::get('/app_user/dashboard', [DashboardController::class, 'appUserDashboard'])->name('app_user.dashboard');

    /*
    |--------------------------------------------------------------------------
    | Grup Rute Khusus Admin
    |--------------------------------------------------------------------------
    |
    | Rute di dalam grup ini hanya dapat diakses oleh pengguna dengan peran 'admin'.
    | - `prefix('admin')`: Menambahkan '/admin' di awal URL semua rute di grup ini.
    | - `name('admin.')`: Menambahkan awalan 'admin.' untuk nama rute di grup ini.
    | - `can('manage-users')`: Memastikan hanya pengguna yang memiliki izin 'manage-users'
    |   (yaitu, admin, seperti yang didefinisikan di `AuthServiceProvider`) yang dapat mengaksesnya.
    |
    */
    Route::middleware('can:manage-users')->prefix('admin')->name('admin.')->group(function () {

        // Manajemen Pengguna (CRUD)
        // Ini adalah rute resource untuk mengelola pengguna (index, create, store, show, edit, update, destroy).
        Route::resource('users', UserController::class);

        // Tambahkan rute khusus admin lainnya di sini jika ada.
        // Contoh: Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    });

    /*
    |--------------------------------------------------------------------------
    | Grup Rute Khusus Executive User
    |--------------------------------------------------------------------------
    |
    | Anda bisa menambahkan rute khusus untuk peran 'executive_user' di sini.
    | Contoh:
    */
    // Route::middleware('can:access-executive-features')->prefix('executive')->name('executive.')->group(function () {
    //     Route::get('/reports', [ReportController::class, 'executiveReports'])->name('reports');
    //     // Tambahkan rute executive lainnya di sini
    // });

    /*
    |--------------------------------------------------------------------------
    | Grup Rute Khusus App User
    |--------------------------------------------------------------------------
    |
    | Anda bisa menambahkan rute khusus untuk peran 'app_user' di sini.
    | Contoh:
    */
    // Route::middleware('can:access-app-features')->prefix('app_user')->name('app_user.')->group(function () {
    //     Route::get('/my-profile', [UserProfileController::class, 'show'])->name('profile');
    //     // Tambahkan rute app user lainnya di sini
    // });

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


});


