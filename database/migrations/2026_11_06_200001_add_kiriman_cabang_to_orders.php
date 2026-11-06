<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kirim ke Cabang (FASE_PRODUKSI_GEROBAK_DESIGN.md Tahap 2): penjualan
 * gudang/roastery → cabang (customer_type = internal) menunggu cabang
 * menekan Terima. Selama menunggu, orders.status = 'proses' (enum lama yang
 * belum pernah dipakai) sehingga semua laporan otomatis tidak menghitungnya.
 *
 * order_kiriman_items = rincian barang yang benar-benar keluar dari stok
 * penjual per baris order (bahan komposisi × qty, atau barang itu sendiri),
 * lengkap dengan modal FIFO — dasar barang masuk di cabang, kiriman kurang,
 * tolak, dan retur (Tahap 3). Hanya kolom/tabel baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status_kirim', ['menunggu', 'diterima', 'ditolak', 'dibatalkan'])->nullable()->after('outlet_tujuan_id');
            $table->timestamp('diterima_at')->nullable()->after('status_kirim');
            $table->foreignId('diterima_by')->nullable()->after('diterima_at')->constrained('users')->nullOnDelete();
            $table->string('alasan_tolak', 500)->nullable()->after('diterima_by');
            $table->index(['outlet_tujuan_id', 'status_kirim']);
        });

        Schema::create('order_kiriman_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete(); // barang yang dikirim/diterima
            $table->decimal('qty_kirim', 10, 3);
            $table->decimal('hpp_kirim', 15, 2)->default(0);     // modal FIFO penjual untuk qty_kirim
            $table->decimal('qty_terima', 10, 3)->nullable();
            $table->decimal('nilai_terima', 15, 2)->nullable();  // harga yang dibayar cabang untuk qty_terima
            $table->decimal('qty_retur', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_kiriman_items');

        // MySQL diam-diam memakai index gabungan (outlet_tujuan_id, status_kirim) untuk FK
        // outlet_tujuan_id (index bawaan FK dihapus otomatis saat index ini dibuat). Pasang lagi
        // index biasa untuk FK itu dulu, baru index gabungan bisa dihapus.
        if (! Schema::hasIndex('orders', 'orders_outlet_tujuan_id_foreign')) {
            Schema::table('orders', fn (Blueprint $table) => $table->index('outlet_tujuan_id', 'orders_outlet_tujuan_id_foreign'));
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['outlet_tujuan_id', 'status_kirim']);
            $table->dropConstrainedForeignId('diterima_by');
            $table->dropColumn(['status_kirim', 'diterima_at', 'alasan_tolak']);
        });
    }
};
