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
        Schema::create('role_menu', function (Blueprint $table) {
            $table->string('role'); // Nama peran (misal: 'admin', 'app_user', 'executive_user')
            $table->unsignedBigInteger('menu_item_id'); // ID item menu

            // Definisi foreign key
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');

            // Primary key gabungan untuk mencegah duplikasi entri
            $table->primary(['role', 'menu_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_menu');
    }
};
