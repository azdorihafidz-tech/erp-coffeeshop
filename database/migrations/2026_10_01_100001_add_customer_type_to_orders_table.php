<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roastery V2 Minggu 6-7 — multi-kanal jual roastery (Q1 keputusan
     * Owner). Default 'retail' supaya SEMUA order existing/outlet TIDAK
     * berubah perilaku (CLAUDE.md 9.2 backward-compat).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('customer_type', ['retail', 'wholesale', 'internal'])->default('retail')->after('tipe_transaksi');
            $table->foreignId('outlet_tujuan_id')->nullable()->after('customer_type')->constrained('cabangs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['outlet_tujuan_id']);
            $table->dropColumn(['customer_type', 'outlet_tujuan_id']);
        });
    }
};
