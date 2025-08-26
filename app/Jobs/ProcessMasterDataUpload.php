<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MasterDataPelanggan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;

class ProcessMasterDataUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;

    public $tries = 3; 
    public $timeout = 3600;
    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, int $userId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Memulai pemrosesan file: " . $this->filePath . " oleh User ID: " . ($this->userId ?? 'N/A'));

        // Pastikan file ada sebelum mencoba memprosesnya
        if (!Storage::exists($this->filePath)) {
            Log::error("File tidak ditemukan di jalur: " . $this->filePath);
            $this->fail(new \Exception("File upload tidak ditemukan di storage.")); // Job akan gagal dan bisa dicoba kembali
            return;
        }

        try {
            // Memuat spreadsheet dari jalur file
            $spreadsheet = IOFactory::load(Storage::path($this->filePath));
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Asumsi baris pertama adalah header
            $header = array_map('trim', array_map('strtoupper', array_shift($rows))); 
            $batchSize = 2000; // Ukuran batch untuk insert/update ke database
            $dataBatch = []; // Array untuk menampung data sebelum di-batch insert
            $errors = []; // Array untuk menampung error yang ditemukan
            $processedCount = 0; // Menghitung total baris yang berhasil diproses

            // Mapping kolom dari file Excel ke field database
            $columnMap = [
                'V_BULAN_REKAP' => 'V_BULAN_REKAP', 'UNITUPI' => 'UNITUPI', 'UNITAP' => 'UNITAP', 
                'UNITUP' => 'UNITUP', 'IDPEL' => 'IDPEL', 'TARIF' => 'TARIF', 'DAYA' => 'DAYA', 
                'KOGOL' => 'KOGOL', 'KDDK' => 'KDDK', 'NOMOR_METER_KWH' => 'NOMOR_METER_KWH', 
                'MERK_METER_KWH' => 'MERK_METER_KWH', 'TAHUN_TERA_METER_KWH' => 'TAHUN_TERA_METER_KWH', 
                'TAHUN_BUAT_METER_KWH' => 'TAHUN_BUAT_METER_KWH', 'CT_PRIMER_KWH' => 'CT_PRIMER_KWH', 
                'CT_SEKUNDER_KWH' => 'CT_SEKUNDER_KWH', 'PT_PRIMER_KWH' => 'PT_PRIMER_KWH', 
                'PT_SEKUNDER_KWH' => 'PT_SEKUNDER_KWH', 'FKMKWH' => 'FKMKWH', 'JENISLAYANAN' => 'JENISLAYANAN', 
                'STATUS_DIL' => 'STATUS_DIL', 'NOMOR_GARDU' => 'NOMOR_GARDU', 'NAMA_GARDU' => 'NAMA_GARDU', 
                'KOORDINAT_X' => 'KOORDINAT_X', 'KOORDINAT_Y' => 'KOORDINAT_Y', 'KDPEMBMETER' => 'KDPEMBMETER', 
                'KDAM' => 'KDAM', 'VKRN' => 'VKRN',
            ];

            // Iterasi setiap baris data dari Excel
            foreach ($rows as $rowIndex => $row) {
                $rowData = [];
                $rowErrors = [];
                $hasErrorInRow = false; // Flag untuk menandai jika ada error di baris ini

                foreach ($columnMap as $excelColumn => $dbField) {
                    $colIndex = array_search(strtoupper($excelColumn), $header); // Cari indeks kolom Excel
                    if ($colIndex !== false && isset($row[$colIndex])) {
                        $value = trim($row[$colIndex]);

                        // Validasi dan konversi khusus untuk koordinat
                        if (($dbField === 'KOORDINAT_X' || $dbField === 'KOORDINAT_Y') && !empty($value)) {
                            if (!is_numeric($value)) {
                                $rowErrors[] = "Kolom '{$excelColumn}' (baris " . ($rowIndex + 2) . ") harus berupa angka desimal yang valid.";
                                $hasErrorInRow = true;
                            }
                            $rowData[$dbField] = (float) $value; // Cast ke float
                        } else {
                            $rowData[$dbField] = $value;
                        }
                    } else {
                        // Jika kolom IDPEL wajib dan tidak ditemukan/kosong
                        if ($dbField === 'IDPEL') {
                            $rowErrors[] = "Kolom '{$excelColumn}' (IDPEL) tidak ditemukan atau kosong di baris " . ($rowIndex + 2) . ".";
                            $hasErrorInRow = true;
                        }
                        $rowData[$dbField] = null; // Set null jika tidak ada data
                    }
                }

                // Jika ada error validasi di baris ini, tambahkan ke daftar error
                if (!empty($rowErrors)) {
                    $errors[] = "Baris " . ($rowIndex + 2) . ": " . implode("; ", $rowErrors);
                    $hasErrorInRow = true;
                }

                // Hanya tambahkan ke batch jika tidak ada error di baris ini
                if (!$hasErrorInRow) {
                    $dataBatch[] = $rowData;
                    $processedCount++;

                    // Jika batch sudah penuh, sisipkan ke database
                    if (count($dataBatch) >= $batchSize) {
                        $this->insertBatch($dataBatch, $errors, $rowIndex + 2);
                        $dataBatch = []; // Reset batch
                    }
                }
            }

            // Sisipkan batch terakhir jika ada data yang tersisa setelah loop
            if (!empty($dataBatch)) {
                $this->insertBatch($dataBatch, $errors, $rowIndex + 2);
            }

            // Notifikasi atau logging akhir setelah semua pemrosesan
            if (!empty($errors)) {
                Log::warning("Pemrosesan file selesai dengan beberapa kesalahan: " . $this->filePath . ". Total baris berhasil diproses: {$processedCount}. Kesalahan: " . json_encode($errors));
                // Opsional: Kirim notifikasi ke user tentang kesalahan ini
                // if ($this->userId) {
                //     Notification::send(User::find($this->userId), new UploadFailed($errors));
                // }
            } else {
                Log::info("Pemrosesan file berhasil diselesaikan: " . $this->filePath . " Total baris diproses: " . $processedCount);
                // Opsional: Kirim notifikasi ke user tentang keberhasilan
                // if ($this->userId) {
                //     Notification::send(User::find($this->userId), new UploadCompleted());
                // }
            }

        } catch (\Exception $e) {
            Log::error("Gagal memproses file " . $this->filePath . ": " . $e->getMessage() . " di baris " . $e->getLine());
            $this->fail($e); // Job akan ditandai gagal dan dicoba kembali sesuai $tries
        } finally {
            // Hapus file dari storage setelah pemrosesan selesai (berhasil atau gagal)
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
                Log::info("File " . $this->filePath . " berhasil dihapus dari storage.");
            }
        }
    }

        protected function insertBatch(array $batch, array &$errors, int $lastRowProcessed)
    {
        if (empty($batch)) {
            return;
        }

        DB::beginTransaction(); // Mulai transaksi database untuk batch ini
        try {
            $now = now();
            // Tambahkan timestamps (created_at, updated_at) secara manual untuk upsert
            $batchWithTimestamps = array_map(function($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
                return $item;
            }, $batch);

            // Tentukan kolom yang akan diperbarui jika IDPEL sudah ada
            // Kecualikan 'IDPEL' dan 'created_at' dari update, hanya 'updated_at' dan data lainnya
            $updateColumns = array_keys(array_except($batch[0], ['IDPEL', 'created_at']));
            $updateColumns[] = 'updated_at'; // Pastikan updated_at selalu diperbarui

            // Gunakan upsert:
            // - $batchWithTimestamps: Data yang akan disisipkan/diperbarui
            // - ['IDPEL']: Kolom yang menjadi kunci unik untuk menentukan apakah baris sudah ada
            // - $updateColumns: Kolom-kolom yang akan diperbarui jika baris dengan IDPEL sudah ada
            MasterDataPelanggan::upsert(
                $batchWithTimestamps, 
                ['IDPEL'],
                $updateColumns
            );
            DB::commit(); // Komit transaksi jika berhasil
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack(); // Rollback jika ada kesalahan query
            // Tangani error unik IDPEL dari database (jika ada)
            if ($e->getCode() == 23000) { // SQLSTATE code for integrity constraint violation
                $errors[] = "Beberapa IDPEL dalam batch ini sudah ada di database atau duplikat di file (sekitar baris " . ($lastRowProcessed - count($batch) + 1) . " hingga " . $lastRowProcessed . "). Error: " . $e->getMessage();
            } else {
                $errors[] = "Kesalahan database saat menyisipkan batch (sekitar baris " . ($lastRowProcessed - count($batch) + 1) . " hingga " . $lastRowProcessed . "): " . $e->getMessage();
            }
            Log::error("Kesalahan batch insert/upsert: " . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback untuk kesalahan umum lainnya
            $errors[] = "Kesalahan umum saat menyisipkan batch (sekitar baris " . ($lastRowProcessed - count($batch) + 1) . " hingga " . $lastRowProcessed . "): " . $e->getMessage();
            Log::error("Kesalahan batch insert/upsert: " . $e->getMessage());
        }
    }

        /**
     * Menangani kegagalan job.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        // Log error kegagalan job
        Log::error("Job ProcessMasterDataUpload gagal secara permanen: " . $exception->getMessage() . " untuk file: " . $this->filePath . ". Trace: " . $exception->getTraceAsString());
        // Opsional: Kirim notifikasi ke admin atau pengguna yang mengupload
        // if ($this->userId) {
        //     Notification::send(User::find($this->userId), new UploadFailed('Gagal memproses file upload Anda: ' . $exception->getMessage()));
        // }
    }
}
