<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CabangSeeder::class,
            PermissionSeeder::class,        // Harus sebelum UserSeeder & RolePermissionSeeder
            JenisOlahanSeeder::class,
            KategoriTransaksiSeeder::class, // Buat kategori keuangan dinamis
            ChartOfAccountsSeeder::class, // COA (wajib utk Laba Rugi Formal/Neraca/Buku Besar; idempotent)
            KategoriPengeluaranBackfillSeeder::class, // Backfill kategori_pengeluaran dari data lama (idempotent)
            UserSeeder::class,
            EvaluationAspectSeeder::class,
            ItemCategorySeeder::class,
            ItemSeeder::class,
            ItemRoastedCurahSeeder::class,   // Roastery R2 — 6 item roasted curah (kg), stok 0 di GP001
            RoastingProfileSeeder::class,    // Roastery R1 — 3 profile default
            ResepBumbuSeeder::class,        // Seed 3 resep bumbu starting point (idempotent, butuh ItemSeeder & JenisOlahanSeeder)
            ResepBumbuKopiSeeder::class,     // Roastery R4 — takaran biji menu kopi (idempotent, tidak menimpa)
            ResepBumbuNonKopiSeeder::class,  // Isu #3 — resep 4 menu non-kopi (idempotent, hanya menambah)
            AssetCategorySeeder::class,
            PembelianPenjualanSeeder::class,
            BackfillStockQtyMinimumSeeder::class, // Backfill stocks.qty_minimum dari items.qty_minimum (idempotent)
            HRSeeder::class,
            RolePermissionSeeder::class,    // Harus paling akhir (butuh semua permission sudah ada)
            TooltipAdjustmentSeeder::class, // Tooltip percontohan modul adjustment
            PanduanPosSeeder::class,        // Panduan percontohan modul POS
            PanduanStubSeeder::class,       // Panduan stub 46 menu + update modul POS → penjualan
            PanduanKontenSeeder::class,     // Isi konten nyata 46 panduan dari stub
            TooltipKontenSeeder::class,     // Tooltip field-field bermakna seluruh aplikasi
            StokTestingSeeder::class,       // Stok awal testing 6 cabang x 44 item (butuh ItemSeeder & CabangSeeder)
            MejaSeeder::class,              // Fase E1 (2026-09-23) — 50 meja testing (5 outlet x 10, butuh CabangSeeder)
            KasSeeder::class,                // Fase E3 (2026-09-23) — 5 Kas Tunai testing (1 per outlet, blocker closeBill())
        ]);
    }
}
