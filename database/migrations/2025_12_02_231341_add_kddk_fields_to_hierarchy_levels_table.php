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
        Schema::table('hierarchy_levels', function (Blueprint $table) {
            $table->char('kddk_code', 1)->nullable()->after('code')->comment('Kode Huruf 1 digit untuk KDDK');
            $table->string('unit_type')->nullable()->after('kddk_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hierarchy_levels', function (Blueprint $table) {
            $table->dropColumn(['kddk_code', 'unit_type']);
        });
    }
};
