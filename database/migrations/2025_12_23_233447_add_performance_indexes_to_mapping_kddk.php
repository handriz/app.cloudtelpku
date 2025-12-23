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
        Schema::table('mapping_kddk', function (Blueprint $table) {
            // Index Index gabungan idpel + enabled (SANGAT PENTING untuk query join)
            // Beri nama spesifik 'idx_mapping_idpel_enabled' agar tidak konflik
            $table->index(['idpel', 'enabled'], 'idx_mapping_idpel_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_kddk', function (Blueprint $table) {
            $table->dropIndex('idx_mapping_idpel_enabled');
        });
    }
};
