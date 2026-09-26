<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grinding_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kode_batch', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('cabang_id')->constrained('cabangs'); // fix RST001
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('roasted_item_id')->constrained('items'); // whole bean asal
            $table->decimal('roasted_qty_kg_in', 8, 3);
            $table->string('grind_size', 20); // enum GrindSize
            $table->foreignId('ground_item_id')->nullable()->constrained('items');
            $table->decimal('ground_qty_kg_out', 8, 3)->nullable();
            $table->decimal('waste_kg', 8, 3)->nullable();
            $table->decimal('cost_per_kg', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft');
            $table->timestamps();

            $table->index(['status', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grinding_batches');
    }
};
