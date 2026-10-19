<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Closing Periode (2026-10-17) — arsip closing harian/mingguan/
 * bulanan/custom per cabang atau konsolidasi. Snapshot angka disimpan
 * sebagai JSON (`data`) supaya laporan closing lama tetap sama persis
 * dengan kondisi saat closing walau data hidup berubah. Tabel BARU saja,
 * tidak ada kolom tabel lama yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_periodes', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->enum('jenis', ['harian', 'mingguan', 'bulanan', 'custom'])->default('custom');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            // NULL = Konsolidasi (semua cabang)
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->enum('status', ['draft', 'final', 'dibuka'])->default('draft');
            $table->unsignedInteger('versi')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('closing_periodes')->nullOnDelete();

            // Ringkasan untuk daftar (detail lengkap ada di `data`)
            $table->decimal('total_omzet', 15, 2)->default(0);
            $table->decimal('total_hpp', 15, 2)->default(0);
            $table->decimal('laba_bersih', 15, 2)->default(0);
            $table->decimal('total_kas_akhir', 15, 2)->default(0);
            $table->longText('data')->nullable();

            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dihitung_pada')->nullable();
            $table->foreignId('difinalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('difinalkan_pada')->nullable();
            $table->foreignId('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibuka_pada')->nullable();
            $table->text('alasan_buka')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_mulai', 'tanggal_selesai'], 'closing_status_periode_idx');
            $table->index(['cabang_id', 'status'], 'closing_cabang_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_periodes');
    }
};
