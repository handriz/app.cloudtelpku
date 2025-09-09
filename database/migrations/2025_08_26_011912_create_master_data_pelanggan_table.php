<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_data_pelanggan', function (Blueprint $table) {
            $table->id(); // Primary key otomatis
            $table->string('V_BULAN_REKAP')->nullable(); // Contoh: '2023-01' atau 'JAN2023'
            $table->string('UNITUPI')->nullable();
            $table->string('UNITAP')->nullable();
            $table->string('UNITUP')->nullable();
            $table->string('IDPEL')->unique(); // IDPEL harus unik
            $table->string('TARIF')->nullable();
            $table->string('DAYA')->nullable();
            $table->string('KOGOL')->nullable();
            $table->string('KDDK')->nullable();
            $table->string('NOMOR_METER_KWH')->nullable();
            $table->string('MERK_METER_KWH')->nullable();
            $table->string('TAHUN_TERA_METER_KWH')->nullable(); // Tahun sebagai string
            $table->string('TAHUN_BUAT_METER_KWH')->nullable(); // Tahun sebagai string
            $table->string('CT_PRIMER_KWH')->nullable();
            $table->string('CT_SEKUNDER_KWH')->nullable();
            $table->string('PT_PRIMER_KWH')->nullable();
            $table->string('PT_SEKUNDER_KWH')->nullable();
            $table->string('FKMKWH')->nullable();
            $table->string('JENISLAYANAN')->nullable();
            $table->string('STATUS_DIL')->nullable();
            $table->string('NOMOR_GARDU')->nullable();
            $table->string('NAMA_GARDU')->nullable();
            $table->decimal('KOORDINAT_X', 11, 8)->nullable(); // Decimal untuk koordinat (contoh: 0.81012312)
            $table->decimal('KOORDINAT_Y', 11, 8)->nullable(); // Decimal untuk koordinat (contoh: 101.21312312)
            $table->string('KDPEMBMETER')->nullable();
            $table->string('KDAM')->nullable();
            $table->string('VKRN')->nullable();
            $table->timestamps(); // created_at dan updated_at

            $table->index('UNITUP');
            $table->index('UNITAP');
            //$table->index(['UNITUP', 'V_BULAN_REKAP']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_data_pelanggan');
    }
};
