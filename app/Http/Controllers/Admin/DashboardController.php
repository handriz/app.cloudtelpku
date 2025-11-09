<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($request->has('is_ajax')) {
            return view('admin.dashboard.partials.index_content', compact('user'))->render();
        }
        return view('admin.dashboard.index',compact('user')); 
    }
}
