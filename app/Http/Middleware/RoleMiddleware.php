<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Pastikan user sudah login dan memiliki peran yang sesuai
        if (!$request->user() || !$request->user()->hasRole($role)) {
            // Redirect atau abort 403 (Forbidden)
            return abort(403, 'Anda tidak memiliki akses.');
        }
        return $next($request);
    }
}
