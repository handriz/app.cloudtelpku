<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'description' => 'Admin CloudTelPk']);
        Role::firstOrCreate(['name' => 'tl_user', 'description' => 'Team Leader']);
        Role::firstOrCreate(['name' => 'app_user', 'description' => 'User Aplikasi']);
        Role::firstOrCreate(['name' => 'executive_user', 'description' => 'Executive User']);
    }
}
