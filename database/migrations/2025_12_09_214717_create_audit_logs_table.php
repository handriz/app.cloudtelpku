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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Siapa pelakunya
            $table->string('action_type'); // Tipe Aksi: MOVE, REMOVE, BULK_MOVE, dll
            $table->string('target_reference')->nullable(); // IDPEL atau Kode Grup yg kena dampak
            $table->text('description'); // Penjelasan detail: "Memindahkan IDPEL X ke Rute Y"
            $table->string('ip_address')->nullable(); // Alamat IP User
            $table->timestamps(); // Kapan (created_at)

            // Relasi ke User
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
