<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
      if (Auth::check()) {
            $user = Auth::user();
            $intendedRouteName = $user->dashboard_route_name;

            // Pastikan rute yang dituju ada dan valid
            if ($intendedRouteName && \Illuminate\Support\Facades\Route::has($intendedRouteName)) {
                // Periksa apakah pengguna SAAT INI sudah berada di rute yang dituju
                // Ini KRUSIAL untuk mencegah redirect loop.
                if ($request->routeIs($intendedRouteName)) {
                    // Jika sudah berada di rute yang benar, lanjutkan permintaan normal.
                    return $next($request);
                } else {
                    // Jika belum berada di rute yang benar, lakukan pengalihan.
                    return redirect()->route($intendedRouteName);
                }
            } else {
                // Jika tidak ada rute dashboard yang ditentukan atau rutenya tidak valid,
                // arahkan ke dashboard default sebagai fallback.
                if ($request->routeIs('dashboard')) {
                    // Jika sudah di dashboard default, lanjutkan.
                    return $next($request);
                }
                return redirect()->route('dashboard');
            }
        }

        // Jika pengguna belum terotentikasi, biarkan middleware lain (mis. 'auth') menangani.
        return $next($request);
    }
}
