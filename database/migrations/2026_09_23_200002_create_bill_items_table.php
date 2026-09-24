<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STUB E2 — struktur minimum biar approve order_queue bisa insert ke
 * bills+bill_items. Full logic (kitchen display, dll) di E3-E5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty', 10, 3)->default(1);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->json('varian')->nullable();
            $table->text('catatan')->nullable();
            $table->enum('status_dapur', ['pending', 'siap', 'komplain'])->default('pending');
            $table->timestamps();

            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
