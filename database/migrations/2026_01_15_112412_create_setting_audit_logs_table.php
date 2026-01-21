<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('setting_audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Siapa yang mengubah
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Apa yang diubah
            $table->string('setting_key')->index();   // Contoh: 'mobile_min_version'
            $table->string('setting_group')->nullable(); // Contoh: 'system', 'general'
            
            // Perubahan Nilai (Before vs After)
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            
            // Info Teknis
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable(); // Info Browser/Device
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('setting_audit_logs');
    }
};