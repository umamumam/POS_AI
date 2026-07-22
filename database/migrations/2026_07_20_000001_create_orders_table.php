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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_order')->unique();
            $table->string('judul')->default('NYUWUN KIRIMAN');
            $table->dateTime('tanggal');
            $table->text('catatan')->nullable();
            $table->enum('status', ['draft', 'dikirim', 'selesai'])->default('draft');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('produk_id')->nullable()->constrained('produks')->onDelete('set null');
            $table->string('nama_item');
            $table->integer('jumlah')->default(1);
            $table->string('satuan')->default('Karton');
            $table->enum('kolom', ['kiri', 'kanan'])->default('kiri');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
