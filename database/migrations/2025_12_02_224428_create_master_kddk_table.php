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
        Schema::create('master_kddk', function (Blueprint $table) {
            $table->id();
            
            // KODE KDDK (12 Digit) - Kunci Utama Bisnis
            // Contoh: A1B RB AA 001 00
            $table->string('kode_kddk', 12)->unique()->index(); 
            
            // Unit Pemilik (Menyimpan Nama/Kode Unit, misal: 18111)
            $table->string('unitup')->index(); 
            
            // Petugas Default (Opsional - untuk auto assignment nanti)
            // Pastikan tipe datanya sama dengan id di tabel users (unsignedBigInteger)
            $table->unsignedBigInteger('default_petugas_id')->nullable();
            
            // Keterangan Rute (Opsional)
            $table->string('keterangan')->nullable();
            
            // Status Aktif
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // Foreign Key ke Users (Opsional, aktifkan jika tabel users sudah ada)
            // $table->foreign('default_petugas_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_kddk');
    }
};