<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kode_batch', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('cabang_id')->constrained('cabangs'); // fix RST001
            $table->foreignId('user_id')->constrained('users');
            $table->enum('source_type', ['roasted_whole', 'roasted_ground']);
            $table->foreignId('source_item_id')->constrained('items');
            $table->decimal('source_qty_kg_in', 8, 3);
            $table->foreignId('target_item_id')->nullable()->constrained('items');
            $table->string('target_pack_size', 10); // enum PackSize
            $table->unsignedInteger('target_qty_pack')->nullable();
            $table->decimal('waste_kg', 8, 3)->nullable();
            $table->decimal('cost_per_pack', 15, 2)->nullable();
            $table->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_batches');
    }
};
