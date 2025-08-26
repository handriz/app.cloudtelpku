<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HierarchyLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; 

class HierarchyLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
                // --- PENTING: Nonaktifkan Foreign Key Checks Sementara ---
        Schema::disableForeignKeyConstraints();

        // Hapus semua menu yang ada untuk menghindari duplikasi saat seeding ulang
        DB::table('hierarchy_levels')->truncate();

        // --- PENTING: Aktifkan Kembali Foreign Key Checks ---
        Schema::enableForeignKeyConstraints();

        // --- Level Induk (Unit Induk) ---
        $unitIndukRiauKepri = HierarchyLevel::create([
            'code' => '18',
            'name' => 'Riau dan Kepri',
            'parent_code' => null, // Ini adalah level paling atas
            'order' => 10,
            'is_active' => true,
        ]);

        // --- Level Area (Anak dari Unit Induk) ---
        $unitAreaPekanbaru = HierarchyLevel::create([
            'code' => '18PKU',
            'name' => 'Pekanbaru',
            'parent_code' => $unitIndukRiauKepri->code,
            'order' => 10,
            'is_active' => true,
        ]);

        // --- Level Layanan (Anak dari Unit Area Pekanbaru) ---
        HierarchyLevel::create([
            'code' => '18110',
            'name' => 'Kota Timur',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 10,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18111',
            'name' => 'Kota Barat',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 20,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18120',
            'name' => 'Simpang Tiga',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 30,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18130',
            'name' => 'Rumbai',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 40,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18140',
            'name' => 'Panam',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 50,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18150',
            'name' => 'Perawang',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 60,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18170',
            'name' => 'Siak Sri Indrapura',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 70,
            'is_active' => true,
        ]);

        HierarchyLevel::create([
            'code' => '18180',
            'name' => 'Pangkalan Kerinci',
            'parent_code' => $unitAreaPekanbaru->code,
            'order' => 80,
            'is_active' => true,
        ]);
    }
}
