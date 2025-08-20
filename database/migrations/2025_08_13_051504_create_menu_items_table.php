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
            $table->string('name');
            $table->string('route_name')->nullable(); // Nama rute Laravel
            $table->string('icon')->nullable(); // Contoh: 'fas fa-tachometer-alt'
            $table->string('url')->nullable(); // URL langsung jika tidak ada route_name
            $table->string('permission_name')->nullable(); // Nama permission yang dibutuhkan untuk melihat menu
            $table->unsignedBigInteger('parent_id')->nullable(); // Untuk sub-menu
            $table->integer('order')->default(0); // Urutan tampilan menu
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint untuk parent_id
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('menu_items')
                  ->onDelete('cascade'); // Hapus sub-menu jika parent dihapus
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
