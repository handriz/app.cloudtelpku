<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
        public function index()
    {
        $user = Auth::user();
        Gate::authorize('access-admin-dashboard'); // Otorisasi berdasarkan Gate dinamis

        return view('admin.dashboard.index',compact('user')); // Sesuaikan dengan lokasi view dashboard admin Anda
    }
}
