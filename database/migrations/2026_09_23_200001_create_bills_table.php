<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STUB E2 — struktur minimum biar order_queues bisa FK ke bills. Detail
 * penuh (transfer meja, split bill, dst) dikerjakan di E3/E4, lihat
 * FASE_E_QR_TABLE_ORDERING.md section "Arsitektur Database".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meja_id')->constrained('mejas')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->string('nomor_bill', 30)->unique();
            $table->enum('status', ['open', 'waiting_payment', 'closed', 'cancelled'])->default('open');
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('sumber_awal', ['qr', 'kasir'])->default('kasir');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cabang_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
