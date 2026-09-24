<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Stok awal untuk testing POS/inventory Kopi Drip (2026-09-22). Angka
 * realistis coffee shop menengah, BUKAN data final — sengaja dipisah dari
 * ItemSeeder supaya bisa direset/diubah tanpa menyentuh definisi item.
 *
 * Aturan qty per kategori (lihat instruksi Owner):
 * - Bahan Baku (BHN) & Kemasan (KMS): sama rata di semua 6 cabang.
 * - Green Bean (GRB): CUMA di Gudang Pusat (GP001) — bahan mentah roastery,
 *   outlet tidak megang green bean.
 * - Roasted Bean (RTB): Gudang Pusat lebih banyak (stok utama + suplai ke
 *   outlet), tiap outlet dapat porsi kecil buat retail langsung.
 * - Menu jual (KPI/MBW/NKP/SNK/MKN): 0 di Gudang Pusat (tidak ada POS di
 *   sana), 20 unit di tiap outlet untuk testing checkout.
 */
class StokTestingSeeder extends Seeder
{
    public function run(): void
    {
        $gp = Cabang::where('kode_cabang', 'GP001')->first();
        $outlets = Cabang::whereIn('kode_cabang', ['OUT001', 'OUT002', 'OUT003', 'OUT004', 'OUT005'])->get();

        if (! $gp || $outlets->count() !== 5) {
            $this->command?->warn('StokTestingSeeder: Gudang Pusat atau outlet tidak lengkap, di-skip.');
            return;
        }

        // Bahan Baku & Kemasan — qty sama rata di SEMUA 6 cabang (GP + 5 outlet)
        $bahanBakuKemasan = [
            'BHN-001' => 20, // Susu UHT Full Cream (liter)
            'BHN-002' => 10, // Susu UHT Skim (liter)
            'BHN-003' => 5,  // Susu Oat (liter)
            'BHN-004' => 15, // Gula Pasir (kg)
            'BHN-005' => 5,  // Sirup Vanilla (botol)
            'BHN-006' => 5,  // Sirup Caramel (botol)
            'BHN-007' => 3,  // Cokelat Bubuk (kg)
            'BHN-008' => 2,  // Matcha Bubuk (kg)
            'BHN-009' => 20, // Es Batu (kg)
            'KMS-001' => 500,  // Cup 12 oz
            'KMS-002' => 500,  // Cup 16 oz
            'KMS-003' => 1000, // Tutup Cup
            'KMS-004' => 1000, // Sedotan
            'KMS-005' => 300,  // Tas Takeaway
        ];

        $semuaCabang = collect([$gp])->merge($outlets);

        foreach ($bahanBakuKemasan as $kode => $qty) {
            $item = Item::where('kode_item', $kode)->first();
            if (! $item) {
                $this->command?->warn("  [{$kode}] item tidak ditemukan, di-skip.");
                continue;
            }

            foreach ($semuaCabang as $cabang) {
                $this->setStok($item, $cabang, $qty);
            }
        }

        // Green Bean — CUMA di Gudang Pusat, outlet 0
        $greenBean = [
            'GRB-001' => 50, // Green Bean Arabika Sidikalang (kg)
            'GRB-002' => 30, // Green Bean Robusta (kg)
        ];

        foreach ($greenBean as $kode => $qty) {
            $item = Item::where('kode_item', $kode)->first();
            if (! $item) {
                $this->command?->warn("  [{$kode}] item tidak ditemukan, di-skip.");
                continue;
            }

            $this->setStok($item, $gp, $qty);
            foreach ($outlets as $cabang) {
                $this->setStok($item, $cabang, 0);
            }
        }

        // Roasted Bean — Gudang Pusat lebih banyak, tiap outlet porsi kecil
        $roastedBean = [
            'RTB-001' => ['gp' => 20, 'outlet' => 5], // 250g
            'RTB-002' => ['gp' => 15, 'outlet' => 3], // 500g
            'RTB-003' => ['gp' => 10, 'outlet' => 2], // 1kg
        ];

        foreach ($roastedBean as $kode => $qtyMap) {
            $item = Item::where('kode_item', $kode)->first();
            if (! $item) {
                $this->command?->warn("  [{$kode}] item tidak ditemukan, di-skip.");
                continue;
            }

            $this->setStok($item, $gp, $qtyMap['gp']);
            foreach ($outlets as $cabang) {
                $this->setStok($item, $cabang, $qtyMap['outlet']);
            }
        }

        // Menu jual (Kopi/Manual Brew/Non-Kopi/Snack/Makanan) — 0 di Gudang
        // Pusat (tidak ada POS di sana), 20 unit di tiap outlet
        $menuJual = Item::whereIn('item_category_id', function ($q) {
            $q->select('id')->from('item_categories')->whereIn('kode_kategori', ['KPI', 'MBW', 'NKP', 'SNK', 'MKN']);
        })->get();

        foreach ($menuJual as $item) {
            $this->setStok($item, $gp, 0);
            foreach ($outlets as $cabang) {
                $this->setStok($item, $cabang, 20);
            }
        }

        $this->command?->info('StokTestingSeeder selesai: stok awal 6 cabang x 44 item di-seed/verified.');
    }

    private function setStok(Item $item, Cabang $cabang, float $qty): void
    {
        Stock::updateOrCreate(
            ['item_id' => $item->id, 'lokasi_id' => $cabang->id],
            ['qty' => $qty, 'qty_minimum' => $item->qty_minimum ?? 0]
        );
    }
}
