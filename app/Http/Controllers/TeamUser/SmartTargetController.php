<?php

namespace App\Http\Controllers\TeamUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SmartTargetController extends Controller
{
       public function index()
    {
        // Logika untuk mengambil data bisa ditambahkan di sini nanti.
        // Misalnya, mengambil data pelanggan yang menjadi target.
        
        return view('team.analisis.index');
    }
}
