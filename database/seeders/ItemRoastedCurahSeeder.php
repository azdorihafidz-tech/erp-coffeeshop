<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Roastery R2 (2026-09-24) — 6 item roasted bean curah (kg): varietas x roast
 * level. Tipe bahan_baku (dipotong via resep_bumbu saat jual menu kopi),
 * kategori RTB, stok awal 0 di Gudang Pusat (GP001). Idempotent: stok tidak
 * pernah di-reset kalau baris stok sudah ada.
 */
class ItemRoastedCurahSeeder extends Seeder
{
    public function run(): void
    {
        $cat = ItemCategory::where('kode_kategori', 'RTB')->value('id');
        $gp  = Cabang::where('kode_cabang', 'GP001')->value('id');

        foreach (['ARABIKA' => 'Arabika', 'ROBUSTA' => 'Robusta'] as $kodeV => $namaV) {
            foreach (['LIGHT' => 'Light', 'MEDIUM' => 'Medium', 'DARK' => 'Dark'] as $kodeL => $namaL) {
                $item = Item::updateOrCreate(
                    ['kode_item' => "RTB-CURAH-{$kodeV}-{$kodeL}"],
                    [
                        'nama_item'        => "Roasted Bean Curah {$namaV} {$namaL}",
                        'item_category_id' => $cat,
                        'tipe'             => 'bahan_baku',
                        'satuan'           => 'kg',
                        'harga_jual'       => 0,
                        'qty_minimum'      => 0,
                        'is_active'        => true,
                    ]
                );

                if ($gp && ! Stock::where('item_id', $item->id)->where('lokasi_id', $gp)->exists()) {
                    Stock::create(['item_id' => $item->id, 'lokasi_id' => $gp, 'qty' => 0]);
                }
            }
        }

        $this->command?->info('ItemRoastedCurahSeeder: 6 item roasted curah di-seed (idempotent).');
    }
}
