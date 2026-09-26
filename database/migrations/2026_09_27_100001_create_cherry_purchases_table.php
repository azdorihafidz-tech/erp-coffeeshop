<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cherry_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('petani_id')->constrained('petani');
            $table->foreignId('cabang_id')->constrained('cabangs'); // lokasi tujuan, fix RST001
            $table->foreignId('cherry_item_id')->constrained('items'); // Buah Arabika/Robusta Sidikalang
            $table->enum('jenis_buah', ['arabika', 'robusta'])->default('arabika');
            $table->decimal('qty_kg', 8, 3);
            $table->decimal('harga_per_kg', 15, 2);
            $table->decimal('qty_terima_kg', 8, 3)->nullable();
            $table->enum('kualitas_grade', ['A', 'B', 'C'])->nullable();
            $table->enum('status', ['draft', 'disetujui', 'diterima', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->constrained('users'); // dibuat oleh
            $table->foreignId('disetujui_by')->nullable()->constrained('users');
            $table->foreignId('diterima_by')->nullable()->constrained('users');
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamp('diterima_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cherry_purchases');
    }
};
