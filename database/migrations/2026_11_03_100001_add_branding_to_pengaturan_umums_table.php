<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Identitas Usaha (2026-11-03) — nama singkat, tagline & telepon di
 * Pengaturan Umum, supaya kode yang sama bisa dipakai usaha lain tanpa
 * mengubah program (lihat App\Support\Branding).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            if (!Schema::hasColumn('pengaturan_umums', 'nama_singkat')) {
                $table->string('nama_singkat', 30)->nullable()->after('nama_perusahaan');
            }
            if (!Schema::hasColumn('pengaturan_umums', 'tagline')) {
                $table->string('tagline', 150)->nullable()->after('nama_singkat');
            }
            if (!Schema::hasColumn('pengaturan_umums', 'telepon')) {
                $table->string('telepon', 30)->nullable()->after('alamat_perusahaan');
            }
        });

        // Instalasi Kopi Drip yang sudah jalan: isi nilai yang sebelumnya
        // ditulis langsung di tampilan, supaya tampilan tidak berubah.
        // Pengaturan Umum yang belum pernah disimpan belum punya baris —
        // dibuat hanya untuk instalasi yang sudah berisi data (cabang ada);
        // instalasi baru (migrate sebelum seed) dibiarkan kosong.
        if (DB::table('pengaturan_umums')->doesntExist() && DB::table('cabangs')->exists()) {
            DB::table('pengaturan_umums')->insert([
                'nama_perusahaan' => 'Kopi Drip Sidikalang',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        DB::table('pengaturan_umums')
            ->where('nama_perusahaan', 'like', '%Kopi Drip%')
            ->whereNull('nama_singkat')
            ->update(['nama_singkat' => 'Kopi Drip', 'tagline' => 'Coffee Shop & Roastery']);
    }

    public function down(): void
    {
        Schema::table('pengaturan_umums', function (Blueprint $table) {
            $table->dropColumn(['nama_singkat', 'tagline', 'telepon']);
        });
    }
};
