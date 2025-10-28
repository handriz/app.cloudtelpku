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
        $tlUserRole = Role::where('name', 'team')->first();
        $appUserRole = Role::where('name', 'appuser')->first();
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
                'mobile_app' => true,
            ]
        );

        // Buat user TL User
        User::firstOrCreate(
            ['email' => 'TL18110@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Kotim',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18110',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18111@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Kobar',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18111',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18120@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Simpang Tiga',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18120',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18130@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Rumbai',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18130',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18140@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Panam',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18140',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18150@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Perawang',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18150',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18170@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP Siak',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18170',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'TL18170@appcloudtelpku.com'],
            [
                'name' => 'Team Leader ULP P.Kerinci',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18180',
                'is_approved' => true,
            ]
        );

         // Buat contoh user TL User UP3
        User::firstOrCreate(
            ['email' => 'dalsutpku@appcloudtelpku.com'],
            [
                'name' => 'Team Leader Dalsut UP3 Pekanbaru',
                'password' => Hash::make('password'),
                'role_id' => $tlUserRole->id, // Set role_id
                'hierarchy_level_code' => '18PKU',
                'is_approved' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'harmetpku@appcloudtelpku.com'],
            [
                'name' => 'Team Leader Harmet UP3 Pekanbaru',
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
