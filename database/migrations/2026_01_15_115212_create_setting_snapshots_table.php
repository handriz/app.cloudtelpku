<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('setting_snapshots', function (Blueprint $table) {
        $table->id();
        $table->string('setting_key')->index(); // kddk_config_data
        $table->string('hierarchy_code')->nullable(); // Scope Unit
        
        // Menggunakan longText agar JSON besar tidak terpotong
        $table->longText('value'); 
        
        $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_snapshots');
    }
};
