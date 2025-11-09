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
            $table->string('v_bulan_rekap')->nullable(); // Contoh: '2023-01' atau 'JAN2023'
            $table->string('unitupi')->nullable();
            $table->string('unitap')->nullable();
            $table->string('unitup')->nullable();
            $table->string('idpel')->unique(); // idpel harus unik
            $table->string('tarif')->nullable();
            $table->string('daya')->nullable();
            $table->string('kogol')->nullable();
            $table->string('kddk')->nullable();
            $table->string('nomor_meter_kwh')->nullable();
            $table->string('merk_meter_kwh')->nullable();
            $table->string('tahun_tera_meter_kwh')->nullable(); // tahun sebagai string
            $table->string('tahun_buat_meter_kwh')->nullable(); // tahun sebagai string
            $table->string('ct_primer_kwh')->nullable();
            $table->string('ct_sekunder_kwh')->nullable();
            $table->string('pt_primer_kwh')->nullable();
            $table->string('pt_sekunder_kwh')->nullable();
            $table->string('fkmkwh')->nullable();
            $table->string('jenislayanan')->nullable();
            $table->string('status_dil')->nullable();
            $table->date('tglnyala_pb')->nullable();
            $table->string('nomor_gardu')->nullable();
            $table->string('nama_gardu')->nullable();
            $table->decimal('koordinat_x', 11, 8)->nullable(); // decimal untuk koordinat (contoh: 0.81012312)
            $table->decimal('koordinat_y', 11, 8)->nullable(); // decimal untuk koordinat (contoh: 101.21312312)
            $table->string('kdpembmeter')->nullable();
            $table->string('kdam')->nullable();
            $table->string('vkrn')->nullable();
            $table->string('kdpt')->nullable();
            $table->string('kdpt_2')->nullable();
            $table->string('pemda')->nullable();
            $table->string('ket_keperluan')->nullable();
            $table->timestamps(); // created_at dan updated_at

            // 1. Index untuk filter hirarki utama
            $table->index('idpel');
            $table->index('unitupi');
            $table->index('unitup');
            $table->index('unitap');

            // 2. Index untuk query agregat dan count
            $table->index('status_dil');

            // 3. Composite Index (Indeks Gabungan) untuk performa query GROUP BY
            // Ini adalah pengoptimalan paling PENTING untuk query rekapData Anda.
            // Urutan kolom penting: filter dulu, baru group by.
            $table->index(['unitupi', 'jenislayanan', 'daya']);
            $table->index(['unitap', 'jenislayanan', 'daya']);
            $table->index(['unitup', 'jenislayanan', 'daya']);

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
