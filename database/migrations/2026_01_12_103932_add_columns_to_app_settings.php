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
       Schema::table('app_settings', function (Blueprint $table) {
            // Menambahkan kolom 'label' setelah kolom 'key' (agar rapi)
            // Nullable karena data lama belum punya label
            $table->string('label')->nullable()->after('key');

            // Menambahkan kolom 'updated_by' setelah kolom 'type'
            $table->unsignedBigInteger('updated_by')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Hapus kolom jika rollback
            $table->dropColumn(['label', 'updated_by']);
        });
    }
};
