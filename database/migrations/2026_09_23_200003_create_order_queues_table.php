<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meja_id')->constrained('mejas')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->json('payload');
            $table->enum('payment_mode', ['bayar_dulu', 'open_bill'])->default('open_bill');
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('catatan_umum')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejected_reason', 255)->nullable();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'cabang_id']);
            $table->index(['meja_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_queues');
    }
};
