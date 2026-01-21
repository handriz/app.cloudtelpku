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
        Schema::create('matrix_summaries', function (Blueprint $table) {
            $table->id();

            // --- IDENTITAS UNIT ---
            $table->string('unit_code')->index();
            $table->string('unit_name')->nullable();
            $table->string('parent_code')->index();
            $table->string('region_code')->nullable()->index();

            // --- 1. TARGET (Data Master) ---
            $table->integer('target_pelanggan')->default(0);  // Total
            $table->integer('target_prabayar')->default(0);   // Pra
            $table->integer('target_pascabayar')->default(0); // Pasca

            // --- 2. SUDAH KDDK (Mapping) ---
            $table->integer('sudah_kddk')->default(0);            // Total Mapping
            $table->integer('sudah_kddk_prabayar')->default(0);   // Mapping Pra
            $table->integer('sudah_kddk_pascabayar')->default(0); // Mapping Pasca

            // --- 3. VALIDASI (Survey) ---
            $table->integer('realisasi_survey')->default(0); // Total Masuk Temporary
            $table->integer('valid')->default(0);            // Validated = 1
            $table->integer('ditolak')->default(0);          // Rejected

            // --- 4. PERSENTASE ---
            $table->float('percentage')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matrix_summaries');
    }
};
