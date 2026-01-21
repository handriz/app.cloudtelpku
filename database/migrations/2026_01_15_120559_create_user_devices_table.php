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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Punya siapa
            $table->string('device_id')->index(); // ID Unik Hardware (Android ID)
            $table->string('model_name')->nullable(); // Cth: Samsung SM-A50
            $table->string('android_version')->nullable(); // Cth: Android 13
            $table->string('app_version')->nullable(); // Cth: 1.0.2
            $table->string('last_ip')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_blocked')->default(false); // Status Blokir
            $table->timestamps();

            // Mencegah duplikat device untuk user yang sama (Opsional, tergantung kebijakan)
            $table->unique(['user_id', 'device_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
