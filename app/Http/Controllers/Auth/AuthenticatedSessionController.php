<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();
        if (!$user->is_approved) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerate();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda belum disetujui oleh admin. Mohon tunggu persetujuan.'
            ]);
        }
        $request->session()->regenerate();
        // Redirect ke dashboard yang sesuai berdasarkan peran
        if ($user->role === 'admin') {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        } elseif ($user->role === 'appuser') {
            return redirect()->intended(route('appuser.dashboard', absolute: false));
        } elseif ($user->role === 'executive_user') {
            return redirect()->intended(route('executive.dashboard', absolute: false));
        }
        
        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
