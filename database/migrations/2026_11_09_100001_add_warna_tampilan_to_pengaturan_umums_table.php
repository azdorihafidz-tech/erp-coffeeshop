<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.54 (2026-11-09) — Warna Tampilan per usaha di Pengaturan Umum.
 * NULL = warna bawaan program (hijau #2D6A4F, sidebar hitam #1A1A1A,
 * latar krem #F5F0E6) → instalasi yang sudah berjalan tidak berubah
 * tampilannya sampai Owner memilih warna sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            if (!Schema::hasColumn('pengaturan_umums', 'warna_utama')) {
                $table->string('warna_utama', 7)->nullable()->after('telepon');
            }
            if (!Schema::hasColumn('pengaturan_umums', 'warna_sidebar')) {
                $table->string('warna_sidebar', 7)->nullable()->after('warna_utama');
            }
            if (!Schema::hasColumn('pengaturan_umums', 'warna_latar')) {
                $table->string('warna_latar', 7)->nullable()->after('warna_sidebar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            foreach (['warna_utama', 'warna_sidebar', 'warna_latar'] as $kolom) {
                if (Schema::hasColumn('pengaturan_umums', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
