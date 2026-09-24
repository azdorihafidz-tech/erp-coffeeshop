<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roasting_batches', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_batch', 30)->unique();
            $table->date('tanggal');
            $table->foreignId('cabang_id')->constrained('cabangs');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('profile_id')->constrained('roasting_profiles');
            $table->foreignId('green_bean_item_id')->constrained('items');
            $table->decimal('green_qty_kg', 8, 3);
            $table->foreignId('roasted_curah_item_id')->constrained('items');
            $table->decimal('roasted_qty_kg', 8, 3);
            $table->decimal('waste_qty_kg', 8, 3)->default(0);
            $table->decimal('yield_rate_percent', 5, 2)->default(0);
            $table->decimal('cost_awal', 15, 2)->default(0);
            $table->decimal('cost_per_kg_roasted', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'tanggal']);
            $table->index('status');
        });

        Schema::create('roasting_batch_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('roasting_batches')->cascadeOnDelete();
            $table->foreignId('item_pack_id')->constrained('items');
            $table->unsignedInteger('qty_pack');
            $table->unsignedInteger('berat_per_pack_gr');
            $table->decimal('total_berat_kg', 8, 3);
            $table->decimal('cost_per_pack', 15, 2)->default(0);
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roasting_batch_packs');
        Schema::dropIfExists('roasting_batches');
    }
};
