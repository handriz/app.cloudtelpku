<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;


class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Pastikan user sudah login
        if (!$user) {
            // Redirect atau abort 403 (Forbidden)
            return abort(403, 'Anda tidak memiliki akses.');
        }
        // 2. Loop melalui peran yang diizinkan (misal: ['team', 'appuser'])
        foreach ($roles as $role) {
            // 3. Gunakan fungsi hasRole dari User.php
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // 4. Jika loop selesai dan tidak ada peran yang cocok, blokir
        return abort(403, 'Anda tidak memiliki akses.');
    }
}
