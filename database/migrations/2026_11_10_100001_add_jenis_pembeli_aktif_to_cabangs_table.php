<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar "Jenis Pembeli di POS" per cabang (2026-11-10, permintaan Owner).
 * Hanya berlaku untuk cabang tipe Gudang Pusat / Roastery. Bawaan OFF: POS gudang
 * berjualan seperti biasa (Retail) dan pilihan Retail / Wholesale / Kirim ke Cabang
 * baru tampil setelah Owner menyalakannya di Cabang → Edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cabangs', 'jenis_pembeli_aktif')) {
            return;
        }
        Schema::table('cabangs', function (Blueprint $table) {
            $table->boolean('jenis_pembeli_aktif')->default(false)->after('frozen_aktif');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cabangs', 'jenis_pembeli_aktif')) {
            Schema::table('cabangs', function (Blueprint $table) {
                $table->dropColumn('jenis_pembeli_aktif');
            });
        }
    }
};
