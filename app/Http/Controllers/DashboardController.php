<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        $user = Auth::user();
        return view('dashboards.admin', compact('user'));
    }

    public function executiveDashboard()
    {
        $user = Auth::user();
        return view('dashboards.executive', compact('user'));
    }

    public function appUserDashboard()
    {
        $user = Auth::user();
        return view('dashboards.app_user', compact('user'));
    }

    public function defaultDashboard()
    {
        $user = Auth::user();
        return view('dashboards.default', compact('user'));
    }
}
