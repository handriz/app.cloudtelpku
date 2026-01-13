<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AppSetting;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cek Apakah Mode Maintenance Aktif?
        $isMaintenance = \App\Models\AppSetting::findValue('system_maintenance_mode', null, false);

        if ($isMaintenance) {
            
            // --- LOGIC WHITELIST YANG DIPERBAIKI ---
            // 1. $request->is('login')     -> Menangani URL '/login' (Baik GET maupun POST) <--- INI KUNCINYA
            // 2. $request->routeIs(...)    -> Menangani nama route (jika ada)
            // 3. $request->is('logout')    -> Agar user bisa logout
            
            if ($request->is('login') || 
                $request->routeIs('login') || 
                $request->is('logout') || 
                $request->routeIs('logout') ||
                $request->is('login/*') || 
                $request->is('/') ||
                $request->is('sanctum/*')) { 
                
                return $next($request);
            }
            // ---------------------------------------------

            $user = Auth::user();

            // Jika User belum login, ATAU User login tapi bukan Admin
            if (!$user || !$user->hasRole('admin')) {
                return response()->view('errors.maintenance', [], 503);
            }
        }

        return $next($request);
    }
}