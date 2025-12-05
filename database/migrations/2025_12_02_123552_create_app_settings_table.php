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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            
            // NULL = Setting Global. Jika Terisi = Setting Unit/Level tersebut.
            $table->string('hierarchy_code')->nullable()->index(); 
            
            // Kategori Pengaturan (misal: 'general', 'kddk', 'notification', 'appearance')
            $table->string('group')->default('general')->index();
            
            // Nama unik setting (misal: 'target_periode', 'enable_validation')
            $table->string('key'); 
            
            // Nilainya (bisa teks, angka, atau JSON)
            $table->longText('value')->nullable();
            
            // Tipe data untuk casting (string, integer, boolean, array)
            $table->string('type')->default('string'); 
            
            $table->timestamps();

            // Mencegah duplikasi setting untuk hirarki yang sama
            $table->unique(['hierarchy_code', 'key']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
