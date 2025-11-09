<?php

namespace App\Http\Controllers\AppUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($request->has('is_ajax')) {
            return view('appuser.dashboard.partials.index_content', compact('user'))->render();
        }
        return view('appuser.dashboard.index',compact('user')); 
    }
}
