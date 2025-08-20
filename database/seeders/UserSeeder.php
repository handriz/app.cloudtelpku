<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $appUserRole = Role::where('name', 'app_user')->first();
        $executiveUserRole = Role::where('name', 'executive_user')->first(); 
        
        // Buat user admin
        User::firstOrCreate(
            ['email' => 'zalil@cloudtelpku.com'],
            [
                'name' => 'Administrator',
                'password' =>Hash::make('password'),
                'role_id' => $adminRole->id, // Set role_id
                'hierarchy_level_code' => null,
                'dashboard_route_name' => null,
                'is_approved' => true,
                'dashboard_route_name' => 'admin.dashboard',
            ]
        );

        // Buat contoh user App User
        User::firstOrCreate(
            ['email' => 'tl18110@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP KOTIM',
                'password' => Hash::make('password'),
                'role_id' => $appUserRole->id, // Set role_id
                'hierarchy_level_code' => '18110',
                'dashboard_route_name' => 'app_user.dashboard',
                'is_approved' => true,
            ]
        );


        // Buat contoh user Executive
        User::firstOrCreate(
            ['email' => 'executive@example.com'],
            [
                'name' => 'executive@appcloudtelpku.com',
                'password' => Hash::make('password'),
                'role_id' => $executiveUserRole->id, // Set role_id
                'hierarchy_level_code' => '18PKU',
                'dashboard_route_name' => 'executive.dashboard',
                'is_approved' => true,
            ]
        );
    }
}
