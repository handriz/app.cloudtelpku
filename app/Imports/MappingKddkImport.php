<?php

namespace App\Imports;

use App\Models\MappingKddk;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Support\Facades\DB;

class MappingKddkImport implements ToModel, WithHeadingRow, WithChunkReading, WithUpserts, WithCustomCsvSettings
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    private $lastObjectId;

    public function __construct()
    {
        $this->lastObjectId = DB::table('mapping_kddk')->max('objectid') ?? 0;
    }

    public function model(array $row)
    {
        $objectId = $row['objectid'] ?? null;
        
        if (empty($objectId)) {
            // Jika objectid di CSV kosong, naikkan counter dan gunakan nilainya
            $this->lastObjectId++;
            $objectId = $this->lastObjectId;
        } else {
            // Jika objectid di CSV ada isinya, pastikan counter kita
            // tetap lebih tinggi untuk data berikutnya yang mungkin kosong.
            if ($objectId > $this->lastObjectId) {
                $this->lastObjectId = (int)$objectId;
            }
        }

        $koordinatX = $row['longitudex'];
        $koordinatY = $row['latitudey'];

        if (!is_numeric($koordinatX) || abs($koordinatX) > 999) {
            $koordinatX = null;
        }

        if (!is_numeric($koordinatY) || abs($koordinatY) > 999) {
        $koordinatY = null;
        }

        return new MappingKddk([
            'objectid'          => $objectId,
            'idpelanggan'       => $row['idpelanggan'] ?? null,
            'user_pendataan'    => $row['user_pendataan'] ?? null,
            'enabled'           => isset($row['enabled']) ? filter_var($row['enabled'], FILTER_VALIDATE_BOOLEAN) : true,
            'nokwhmeter'        => $row['nokwhmeter'] ?? null,
            'merkkwhmeter'      => $row['merkkwhmeter'] ?? null,
            'tahun_buat'        => $row['tahun_buat'] ?? null,
            'mcb'               => $row['mcb'] ?? null,
            'type_pbts'         => $row['type_pbts'] ?? null,
            'type_kotakapp'     => $row['type_kotakapp'] ?? null,
            'latitudey'         => $koordinatY,
            'longitudex'        => $koordinatX,
            'namagd'            => $row['namagd'] ?? null,
            'jenis_kabel'       => $row['jenis_kabel'] ?? null,
            'ukuran_kabel'      => $row['ukuran_kabel'] ?? null,
            'ket_survey'        => $row['ket_survey'] ?? null,
            'deret'             => $row['deret'] ?? null,
            'sr'                => $row['sr'] ?? null,
            'ket_validasi'      => $row['ket_validasi'] ?? null,
            'foto_kwh'          => $row['foto_kwh'] ?? null,
            'foto_bangunan'     => $row['foto_bangunan'] ?? null,
        ]);
    }

    /**
     * Tentukan kolom unik untuk melakukan update jika data sudah ada (Upsert).
     */
    public function uniqueBy()
    {
        return 'idpelanggan';
    }

    public function chunkSize(): int
    {
        return 1000; // Optimal untuk file besar
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';'
        ];
    }
}