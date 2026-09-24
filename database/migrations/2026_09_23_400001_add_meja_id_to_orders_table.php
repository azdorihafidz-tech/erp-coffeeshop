<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E4.1 — fondasi unifikasi Bill (Approach C, lihat FASE_E_QR_TABLE_ORDERING.md
 * & CLAUDE.md riwayat E4). `nomor_meja` (string) TETAP dipertahankan untuk
 * backward-compat data historis pre-E4 — `meja_id` adalah sumber kebenaran
 * BARU untuk order dine-in setelah E4.2 (dropdown meja di POS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('meja_id')->nullable()->after('nomor_meja')
                ->comment('FK ke mejas. NULL untuk data historis pre-E4 atau order non-dine-in');
            $table->foreign('meja_id')->references('id')->on('mejas')->nullOnDelete();
            $table->index('meja_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['meja_id']);
            $table->dropIndex(['meja_id']);
            $table->dropColumn('meja_id');
        });
    }
};
