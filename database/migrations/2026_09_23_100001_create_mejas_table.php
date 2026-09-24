<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mejas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->integer('nomor_meja');
            $table->string('nama_meja', 50);
            $table->integer('kapasitas')->default(4)->nullable();
            $table->enum('lokasi', ['indoor', 'outdoor', 'vip'])->default('indoor');
            $table->string('qr_token', 32)->unique();
            $table->enum('qr_type', ['permanent', 'temporary'])->default('permanent');
            $table->timestamp('qr_expires_at')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['cabang_id', 'nomor_meja']);
            $table->index(['cabang_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mejas');
    }
};
