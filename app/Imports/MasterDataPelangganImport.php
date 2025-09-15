<?php

namespace App\Imports;

use App\Models\MasterDataPelanggan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithUpserts;

class MasterDataPelangganImport implements ToModel, WithHeadingRow, WithChunkReading, WithUpserts
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new MasterDataPelanggan([
            'V_BULAN_REKAP'         => $row['v_bulan_rekap'],
            'UNITUPI'               => $row['unitupi'],
            'UNITAP'                => $row['unitap'],
            'UNITUP'                => $row['unitup'],
            'IDPEL'                 => $row['idpel'],
            'TARIF'                 => $row['tarif'],
            'DAYA'                  => $row['daya'],
            'KOGOL'                 => $row['kogol'],
            'KDDK'                  => $row['kddk'],
            'NOMOR_METER_KWH'       => $row['nomor_meter_kwh'],
            'MERK_METER_KWH'        => $row['merk_meter_kwh'],
            'TAHUN_TERA_METER_KWH'  => $row['tahun_tera_meter_kwh'],
            'TAHUN_BUAT_METER_KWH'  => $row['tahun_buat_meter_kwh'],
            'CT_PRIMER_KWH'         => $row['ct_primer_kwh'],
            'CT_SEKUNDER_KWH'       => $row['ct_sekunder_kwh'],
            'PT_PRIMER_KWH'         => $row['pt_primer_kwh'],
            'PT_SEKUNDER_KWH'       => $row['pt_sekunder_kwh'],
            'FKMKWH'                => $row['fkmkwh'],
            'JENISLAYANAN'          => $row['jenislayanan'],
            'STATUS_DIL'            => $row['status_dil'],
            'NOMOR_GARDU'           => $row['nomor_gardu'],
            'NAMA_GARDU'            => $row['nama_gardu'],
            'KOORDINAT_X'           => $row['koordinat_x'],
            'KOORDINAT_Y'           => $row['koordinat_y'],
            'KDPEMBMETER'           => $row['kdpembmeter'],
            'KDAM'                  => $row['kdam'],
            'VKRN'                  => $row['vkrn'],
        ]);
    }

    public function uniqueBy()
    {
        return 'IDPEL';
    }

    public function chunkSize(): int
    {
        return 1000; // Proses 1000 baris per putaran (aman untuk file besar)
    }
}
