<?php

namespace App\Http\Controllers\TlUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {

        $user = Auth::user();
        return view('tl_user.dashboard.index',compact('user')); 

    }
}
