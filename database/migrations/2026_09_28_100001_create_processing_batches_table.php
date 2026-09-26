<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kode_batch', 30)->unique();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->foreignId('cabang_id')->constrained('cabangs'); // fix RST001
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('cherry_item_id')->constrained('items');
            $table->decimal('cherry_qty_kg', 8, 3);
            $table->decimal('cherry_cost_awal', 15, 2)->default(0); // FIFO aktual saat stok cherry dipotong
            $table->string('processing_method', 20); // enum ProcessingMethod

            $table->timestamp('fermentasi_start')->nullable();
            $table->timestamp('fermentasi_end')->nullable();
            $table->unsignedTinyInteger('fermentasi_suhu_celsius')->nullable();
            $table->text('fermentasi_catatan')->nullable();

            $table->timestamp('drying_start')->nullable();
            $table->timestamp('drying_end')->nullable();
            $table->text('drying_catatan')->nullable();

            $table->decimal('hulling_qty_kg', 8, 3)->nullable();

            $table->decimal('sortir_qty_kg', 8, 3)->nullable();
            $table->decimal('sortir_defect_kg', 8, 3)->nullable();

            $table->foreignId('green_bean_item_id')->nullable()->constrained('items');
            $table->enum('status', ['draft', 'fermentasi', 'drying', 'hulling', 'sortir', 'selesai', 'dibatalkan'])->default('draft');
            $table->decimal('yield_percent', 5, 2)->nullable();
            $table->decimal('cost_per_kg_green', 15, 2)->nullable();
            $table->text('catatan_umum')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_batches');
    }
};
