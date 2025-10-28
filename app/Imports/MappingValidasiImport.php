<?php

namespace App\Imports;

use App\Models\TemporaryMapping; // Model yang Benar
use App\Models\MappingKddk;      // Dibutuhkan untuk cek max objectid
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Jika perlu log

class MappingValidasiImport implements ToModel, WithHeadingRow, WithChunkReading, WithUpserts, WithCustomCsvSettings
{
    private $lastObjectId;

    public function __construct()
    {
        // Cek nilai max dari KEDUA tabel, sama seperti di fungsi store
        $lastTempId = TemporaryMapping::max('objectid');
        $lastKddkId = MappingKddk::max('objectid');
        // Gunakan max dari kedua tabel, fallback ke 0 jika keduanya null
        $this->lastObjectId = max($lastTempId, $lastKddkId) ?? 0;
        Log::info('MappingValidasiImport initialized. Last ObjectId set to: ' . $this->lastObjectId); // Log inisialisasi
    }

    /**
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Log::debug('Processing row:', $row); // Log detail baris (aktifkan jika perlu debug)

        // 1. Logika Object ID (dari CSV atau auto-increment)
        $objectId = isset($row['objectid']) ? trim($row['objectid']) : null;

        if (empty($objectId)) {
            // Jika objectid di CSV kosong, naikkan counter internal
            $this->lastObjectId++;
            $objectId = $this->lastObjectId;
            Log::debug('Object ID from CSV is empty, using incremented value: ' . $objectId);
        } else {
            // Jika objectid di CSV ada, pastikan counter internal tetap >
            $objectId = (int)$objectId; // Pastikan integer
            if ($objectId > $this->lastObjectId) {
                $this->lastObjectId = $objectId;
                Log::debug('Object ID from CSV (' . $objectId . ') is higher than current lastObjectId, updating lastObjectId.');
            } else {
                 Log::debug('Object ID from CSV (' . $objectId . ') is not higher than current lastObjectId (' . $this->lastObjectId . ').');
            }
        }

        // 2. Ambil dan bersihkan data teks utama
        $idpel = isset($row['idpel']) ? trim($row['idpel']) : null;
        $userPendataan = isset($row['user_pendataan']) ? trim($row['user_pendataan']) : null;

        // 3. Validasi Koordinat
        $koordinatX = $row['longitudex'] ?? null;
        $koordinatY = $row['latitudey'] ?? null;

        // Validasi Latitude dan Longitude (contoh sederhana)
        if (!is_numeric($koordinatX) || $koordinatX < -180 || $koordinatX > 180) {
            Log::warning("Invalid Longitude value '{$koordinatX}' for ObjectID {$objectId}, setting to null.");
            $koordinatX = null;
        }
        if (!is_numeric($koordinatY) || $koordinatY < -90 || $koordinatY > 90) {
            Log::warning("Invalid Latitude value '{$koordinatY}' for ObjectID {$objectId}, setting to null.");
            $koordinatY = null;
        }

        // 4. Buat instance TemporaryMapping (TANPA FOTO PATH)
        return new TemporaryMapping([
            'objectid'          => $objectId,
            'idpel'             => $idpel,
            'user_pendataan'    => $userPendataan,
            'enabled'           => isset($row['enabled']) ? filter_var($row['enabled'], FILTER_VALIDATE_BOOLEAN) : true, // Default true jika tidak ada
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
            'ket_validasi'      => $row['ket_validasi'] ?? 'pending', // Default ke 'pending' jika kosong
            // Kolom foto_kwh dan foto_bangunan sengaja dikosongkan (akan diisi oleh ProcessPhotoInbox)
        ]);
    }

    /**
     * Tentukan kolom unik untuk melakukan update jika data sudah ada (Upsert).
     *
     * @return string|array
     */
    public function uniqueBy()
    {
        // Kunci unik berdasarkan objectid
        return 'objectid';
    }

    /**
     * Ukuran chunk untuk membaca file CSV.
     *
     * @return int
     */
    public function chunkSize(): int
    {
        // Proses 1000 baris per batch untuk efisiensi memori
        return 1000;
    }

    /**
     * Pengaturan spesifik untuk file CSV.
     *
     * @return array
     */
    public function getCsvSettings(): array
    {
        // Pastikan delimiter sesuai dengan file CSV Anda
        return [
            'delimiter' => ';'
        ];
    }
}