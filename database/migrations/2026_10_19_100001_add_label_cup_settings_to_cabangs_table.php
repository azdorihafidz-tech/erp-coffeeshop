<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Label Cup (2026-10-19) — pengaturan cetak label gelas per cabang:
 * aktif/nonaktif, kategori item yang dapat label (kode kategori, NULL =
 * default KPI+MBW), dan ukuran stiker. Kolom baru saja, nullable/default,
 * tidak mengubah data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            if (!Schema::hasColumn('cabangs', 'label_cup_aktif')) {
                $table->boolean('label_cup_aktif')->default(true)->after('izinkan_sembunyi_harga_struk');
                $table->json('label_cup_kategori')->nullable()->after('label_cup_aktif');
                $table->unsignedSmallInteger('label_cup_lebar_mm')->default(50)->after('label_cup_kategori');
                $table->unsignedSmallInteger('label_cup_tinggi_mm')->default(30)->after('label_cup_lebar_mm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['label_cup_aktif', 'label_cup_kategori', 'label_cup_lebar_mm', 'label_cup_tinggi_mm']);
        });
    }
};
