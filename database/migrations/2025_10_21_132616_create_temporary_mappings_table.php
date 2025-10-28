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
        Schema::create('temporary_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('objectid')->unique();
            $table->string('idpel')->nullable();
            $table->string('user_pendataan')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('nokwhmeter')->nullable();
            $table->string('merkkwhmeter')->nullable();
            $table->string('tahun_buat')->nullable();
            $table->string('mcb')->nullable();
            $table->string('type_pbts')->nullable();
            $table->string('type_kotakapp')->nullable();
            $table->decimal('latitudey', 11, 8)->nullable();
            $table->decimal('longitudex', 11, 8)->nullable();
            $table->string('namagd')->nullable();
            $table->string('jenis_kabel')->nullable();
            $table->string('ukuran_kabel')->nullable();
            $table->text('ket_survey')->nullable();
            $table->string('deret')->nullable();
            $table->string('sr')->nullable();
            $table->text('ket_validasi')->nullable();
            $table->text('validation_notes')->nullable();
            $table->json('validation_data')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('foto_kwh')->nullable();
            $table->string('foto_bangunan')->nullable();
            $table->timestamps(); //

            $table->index('objectid');
            $table->index('idpel');
            $table->index(['locked_by', 'locked_at']);
            $table->index('nokwhmeter');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_mappings');

        Schema::table('temporary_mappings', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropIndex(['locked_by', 'locked_at']);
            $table->dropColumn(['locked_by', 'locked_at']);
        });
    }
};
