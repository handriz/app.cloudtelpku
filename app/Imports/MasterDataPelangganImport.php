<?php

namespace App\Imports;

use App\Models\MasterDataPelanggan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings; 

class MasterDataPelangganImport implements ToModel, WithHeadingRow, WithChunkReading, WithUpserts, WithCustomCsvSettings
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $koordinatX = $row['koordinat_x'];
        $koordinatY = $row['koordinat_y'];

        // Cek apakah nilai koordinat X valid. Jika tidak, set ke null.
        // Angka 999 adalah batas aman untuk DECIMAL(11,8) -> 3 digit di depan koma
        if (!is_numeric($koordinatX) || abs($koordinatX) > 999) {
            $koordinatX = null;
        }

        if (!is_numeric($koordinatY) || abs($koordinatY) > 999) {
        $koordinatY = null;
        }

        return new MasterDataPelanggan([
            'v_bulan_rekap'     => $row['v_bulan_rekap'],
            'unitupi'           => $row['unitupi'],
            'unitap'            => $row['unitap'],
            'unitup'            => $row['unitup'],
            'idpel'             => $row['idpel'],
            'tarif'             => $row['tarif'],
            'daya'              => $row['daya'],
            'kogol'             => $row['kogol'],
            'kddk'              => $row['kddk'],
            'nomor_meter_kwh'   => $row['nomor_meter_kwh'],
            'merk_meter_kwh'    => $row['merk_meter_kwh'],
            'tahun_tera_meter_kwh'  => $row['tahun_tera_meter_kwh'],
            'tahun_buat_meter_kwh'  => $row['tahun_buat_meter_kwh'],
            'ct_primer_kwh'     => $row['ct_primer_kwh'],
            'ct_sekunder_kwh'   => $row['ct_sekunder_kwh'],
            'pt_primer_kwh'     => $row['pt_primer_kwh'],
            'pt_sekunder_kwh'   => $row['pt_sekunder_kwh'],
            'fkmkwh'            => $row['fkmkwh'],
            'jenislayanan'      => $row['jenislayanan'],
            'status_dil'        => $row['status_dil'],
            'nomor_gardu'       => $row['nomor_gardu'],
            'nama_gardu'        => $row['nama_gardu'],
            'koordinat_x'       => $koordinatX,
            'koordinat_y'       => $koordinatY,
            'kdpembmeter'       => $row['kdpembmeter'],
            'kdam'              => $row['kdam'],
            'vkrn'              => $row['vkrn'],
            'kdpt'              => $row['kdpt'],
            'kdpt_2'            => $row['kdpt_2'],
            'pemda'             => $row['pemda'],
            'ket_keperluan'     => $row['ket_keperluan'],
        ]);
    }

    public function uniqueBy()
    {
        return 'IDPEL';
    }

    public function chunkSize(): int
    {
        return 2000; // Proses 1000 baris per putaran (aman untuk file besar)
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';'
        ];
    }
}
