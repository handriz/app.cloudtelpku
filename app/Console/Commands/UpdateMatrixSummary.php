<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateMatrixSummary extends Command
{
    protected $signature = 'matrix:update-summary';
    protected $description = 'Update rekap data KDDK ke tabel summary';

    public function handle()
    {
        $this->info('Mulai kalkulasi detail Matrix...');
        $startTime = microtime(true);

        // QUERY COMPLEX (Pindahkan logika DB::raw Anda ke sini)
        $results = DB::table('master_data_pelanggan as m')
            // 1. Join Hierarki
            ->join('hierarchy_levels as h_ulp', 'm.unitup', '=', 'h_ulp.code')
            ->leftJoin('hierarchy_levels as h_up3', 'h_ulp.parent_code', '=', 'h_up3.code')
            ->leftJoin('hierarchy_levels as h_wil', 'h_up3.parent_code', '=', 'h_wil.code')
            
            // 2. Join Mapping (KDDK) - Hanya yang enabled
            ->leftJoin('mapping_kddk as map', function ($join) {
                $join->on('m.idpel', '=', 'map.idpel')
                     ->where('map.enabled', true);
            })

            // 3. Join Validasi (Temporary Mappings)
            // ASUMSI: temporary_mappings punya kolom 'idpel' untuk join ke master
            ->leftJoin('temporary_mappings as temp', 'm.idpel', '=', 'temp.idpel')

            ->select(
                'h_ulp.code as unit_code',
                'h_ulp.name as unit_name',
                'h_up3.code as parent_code',
                'h_wil.code as region_code',

                // --- LOGIKA HITUNGAN SPESIFIK ANDA ---
                
                // TARGET
                DB::raw('COUNT(DISTINCT m.id) as target_pelanggan'),
                
                DB::raw("COUNT(DISTINCT CASE 
                    WHEN m.jenislayanan LIKE '%PRA%' THEN m.id 
                    END) as target_prabayar"),

                DB::raw("COUNT(DISTINCT CASE 
                    WHEN m.jenislayanan LIKE '%PASKA%' OR m.jenislayanan LIKE '%PASCA%' THEN m.id 
                    END) as target_pascabayar"),

                // SUDAH KDDK (WAJIB NOT NULL & NOT EMPTY)
                DB::raw("COUNT(DISTINCT CASE 
                    WHEN map.kddk IS NOT NULL AND map.kddk != '' 
                    THEN map.id END) as sudah_kddk"),

                DB::raw("COUNT(DISTINCT CASE 
                    WHEN map.kddk IS NOT NULL AND map.kddk != '' 
                    AND m.jenislayanan LIKE '%PRA%' 
                    THEN map.id END) as sudah_kddk_prabayar"),

                DB::raw("COUNT(DISTINCT CASE 
                    WHEN map.kddk IS NOT NULL AND map.kddk != '' 
                    AND (m.jenislayanan LIKE '%PASKA%')
                    THEN map.id END) as sudah_kddk_pascabayar"),

                // VALIDASI (Source: temporary_mappings / temp)
                DB::raw('COUNT(DISTINCT temp.id) as realisasi_survey'),
                DB::raw('COUNT(DISTINCT CASE WHEN temp.is_validated = 1 THEN temp.id END) as valid'),
                DB::raw('COUNT(DISTINCT CASE WHEN temp.ket_validasi LIKE "rejected_%" THEN temp.id END) as ditolak')
            )
            ->groupBy('h_ulp.code', 'h_ulp.name', 'h_up3.code', 'h_wil.code')
            ->get();

        // FORMAT DATA & INSERT
        $dataToInsert = [];
        $now = Carbon::now();

        foreach ($results as $row) {
            // Hitung persentase di PHP agar Database tidak pusing bagi nol
            $persen = $row->target_pelanggan > 0 
                ? ($row->sudah_kddk / $row->target_pelanggan) * 100 
                : 0;

            $dataToInsert[] = [
                'unit_code'   => $row->unit_code,
                'unit_name'   => $row->unit_name,
                'parent_code' => $row->parent_code ?? '',
                'region_code' => $row->region_code ?? '',
                
                // Masukkan hasil hitungan spesifik tadi ke kolom tabel
                'target_pelanggan'      => $row->target_pelanggan,
                'target_prabayar'       => $row->target_prabayar,
                'target_pascabayar'     => $row->target_pascabayar,
                
                'sudah_kddk'            => $row->sudah_kddk,
                'sudah_kddk_prabayar'   => $row->sudah_kddk_prabayar,
                'sudah_kddk_pascabayar' => $row->sudah_kddk_pascabayar,
                
                'realisasi_survey'      => $row->realisasi_survey,
                'valid'                 => $row->valid,
                'ditolak'               => $row->ditolak,
                
                'percentage'  => $persen,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        // INSERT (Truncate di luar Transaction)
        DB::table('matrix_summaries')->truncate();

        DB::transaction(function () use ($dataToInsert) {
            foreach (array_chunk($dataToInsert, 500) as $chunk) {
                DB::table('matrix_summaries')->insert($chunk);
            }
        });

        $duration = number_format(microtime(true) - $startTime, 2);
        $this->info("Selesai! Update sukses untuk " . count($dataToInsert) . " unit dalam {$duration} detik.");
    }
}