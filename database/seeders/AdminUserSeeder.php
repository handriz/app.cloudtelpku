<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Buat user admin
        User::create([
        'name' => 'Administrator',
        'email' => 'zalil@cloudtelpku.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'hierarchy_level_code' => null,
        'dashboard_route_name' => 'admin.dashboard',
        ]);

        // Buat contoh user App User
        User::create([
            'name' => 'Team Leader ULP KOTIM',
            'email' => 'tl18110@appcloudtelpku.com',
            'password' => Hash::make('password'),
            'role' => 'app_user',
            'hierarchy_level_code' => '18110', // Contoh ULP
            'dashboard_route_name' => 'app_user.dashboard', // <-- Tambahkan ini
        ]);

        // Buat contoh user Executive
        User::create([
            'name' => 'Executive User',
            'email' => 'executive@appcloudtelpku.com',
            'password' => Hash::make('password'),
            'role' => 'executive_user',
            'hierarchy_level_code' => '18PKU', // Contoh AP
            'dashboard_route_name' => 'executive.dashboard', // <-- Tambahkan ini
        ]);
    }
}
