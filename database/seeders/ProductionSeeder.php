<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Go-Live Reset (2026-10-16) — subset DatabaseSeeder untuk production.
 *
 * DIKELUARKAN dari seeder ini (vs DatabaseSeeder penuh):
 * - PembelianPenjualanSeeder — supplier dummy "PT Daging Segar" dkk (warisan)
 * - HRSeeder — karyawan dummy Budi Santoso dkk (warisan)
 * - CherryPurchaseSeeder — 2 contoh transaksi beli cherry
 * - ProcessingBatchSeeder — 1 contoh batch processing
 * - StokTestingSeeder — 264 baris stok test (6 cabang × 44 item)
 * - MejaSeeder — 50 meja testing (Owner setup sendiri di UI sesuai outlet real)
 * - KasSeeder (saldo 500rb) — DIGANTI KasProductionSeeder (saldo 0)
 *
 * MASIH DIIKUT (master wajib):
 * - Cabang (6: GP001+5 outlet+RST001), User (9 akun login), Permission,
 *   Role-Permission, COA, Kategori Transaksi, Item (semua: menu+bahan+roastery),
 *   Resep (16 resep standar menu), Jenis Olahan, Petani (3 contoh), Panduan +
 *   Tooltip (100 panduan + 180 tooltip), Roasting Profile, Asset Category,
 *   Evaluation Aspect, Kas kosong (saldo 0).
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CabangSeeder::class,
            PermissionSeeder::class,         // Harus sebelum UserSeeder & RolePermissionSeeder
            JenisOlahanSeeder::class,
            KategoriTransaksiSeeder::class,
            ChartOfAccountsSeeder::class,    // 59 akun — wajib utk Laba Rugi/Neraca
            KategoriPengeluaranBackfillSeeder::class, // Idempotent backfill
            UserSeeder::class,               // 9 akun default @kopidrip.com
            EvaluationAspectSeeder::class,
            ItemCategorySeeder::class,       // 9 kategori item
            ItemSeeder::class,               // 44 item menu+bahan
            ItemRoastedCurahSeeder::class,   // 6 item roasted curah
            RoastingProfileSeeder::class,    // 3 profile roasting default
            PetaniSeeder::class,             // 3 petani contoh (Owner edit via UI)
            ItemCherrySeeder::class,         // Kategori BHK + 2 item cherry
            ItemGreenBeanSeeder::class,      // 4 item green bean Arabika
            ItemGroundSeeder::class,         // 3 item ground Arabika
            ItemPackSeeder::class,           // 12 item pack retail
            ResepBumbuSeeder::class,         // 3 resep bumbu starter
            ResepBumbuKopiSeeder::class,     // Resep takaran biji menu kopi
            ResepBumbuNonKopiSeeder::class,  // 4 resep menu non-kopi
            AssetCategorySeeder::class,
            BackfillStockQtyMinimumSeeder::class, // Idempotent backfill
            RolePermissionSeeder::class,     // Harus akhir (butuh semua permission)
            TooltipAdjustmentSeeder::class,
            PanduanPosSeeder::class,
            PanduanStubSeeder::class,
            PanduanKontenSeeder::class,      // Konten nyata 100 panduan
            TooltipKontenSeeder::class,      // ~180 tooltip
            KasProductionSeeder::class,      // Kas master saldo 0 (ganti KasSeeder)
        ]);
    }
}
