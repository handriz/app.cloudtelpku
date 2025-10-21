<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlgSearchController;   

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::post('/login', [AuthController::class, 'login']);

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/version_check', function () {
    return response()->json([
        'min_version' => '1.1.0', // Versi minimal yang dibutuhkan
        'update_url' => 'http://45.13.132.18/downloads/latest_app.apk', // URL latest apk
    ]);

Route::get('/search-customer', [PlgSearchController::class, 'search']);
});
