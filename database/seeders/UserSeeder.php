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
        $tlUserRole = Role::where('name', 'tl_user')->first();
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
                'is_approved' => true,
            ]
        );

        // Buat contoh user TL User
        User::firstOrCreate(
            ['email' => 'TL18110@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP KOTIM',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18110',
                'is_approved' => true,
            ]
        );

         // Buat contoh user TL User
        User::firstOrCreate(
            ['email' => 'DalsutPku@appcloudtelpku.com'],
            [
                'name' => 'Team Leader Dalsut UP3 Pekanbaru',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18PKU',
                'is_approved' => true,
            ]
        );

        // Buat contoh user Executive
        User::firstOrCreate(
            ['email' => 'executive@appcloudtelpku.com'],
            [
                'name' => 'executive',
                'password' => Hash::make('password'),
                'role_id' => $executiveUserRole->id, // Set role_id
                'hierarchy_level_code' => '18PKU',
                'is_approved' => true,
            ]
        );
    }
}
