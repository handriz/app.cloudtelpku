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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama tampilan menu (misal: "Manajemen Pengguna")
            $table->string('route_name')->nullable(); // Nama rute Laravel (misal: "admin.users.index")
            $table->string('url')->nullable(); // Alternatif jika bukan rute bernama (misal: URL eksternal)
            $table->string('icon')->nullable(); // Kelas ikon (misal: "fas fa-users" jika menggunakan Font Awesome)
            $table->unsignedBigInteger('parent_id')->nullable(); // Untuk menu bertingkat (sub-menu)
            $table->integer('order')->default(0); // Urutan tampilan dalam grup/level
            $table->timestamps();
            
            // Definisi foreign key untuk menu bertingkat
            $table->foreign('parent_id')->references('id')->on('menu_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
