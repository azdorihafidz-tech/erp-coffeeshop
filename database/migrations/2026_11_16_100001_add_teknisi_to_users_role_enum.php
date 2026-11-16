<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Akun Tim IT (role 'teknisi') — RANCANGAN_PORTING_DIMSUM.md Tahap A.
 * down() menolak jalan kalau masih ada akun Tim IT (MySQL akan mengosongkan
 * nilai enum yang hilang → akun rusak).
 */
return new class extends Migration
{
    private const LAMA = "'owner','admin_pusat','admin_gudang','manajer_cabang','kasir','operator_produksi','helper'";

    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY COLUMN role ENUM(' . self::LAMA . ",'teknisi') NOT NULL DEFAULT 'kasir'");
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'teknisi')->exists()) {
            throw new \RuntimeException('Masih ada akun Tim IT (role teknisi). Hapus/ubah akun itu dulu sebelum rollback migration ini.');
        }
        DB::statement('ALTER TABLE users MODIFY COLUMN role ENUM(' . self::LAMA . ") NOT NULL DEFAULT 'kasir'");
    }
};
