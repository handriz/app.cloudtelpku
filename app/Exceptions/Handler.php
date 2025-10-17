<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Mengembalikan response 422 JSON jika validasi gagal
                return response()->json([
                    'message' => 'Input yang dimasukkan tidak valid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    }

    

/**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // ==========================================================
        // == PERBAIKAN: Cek apakah permintaan berasal dari API ==
        // ==========================================================
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Unauthenticated. Cek username dan password.',
                // Anda bisa menyesuaikan pesan ini
            ], 401);
        }

        // Untuk permintaan non-API, tetap lakukan redirect ke halaman login
        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}
