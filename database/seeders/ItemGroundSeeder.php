<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Roastery V2 Minggu 4-5 (2026-09-29) — 3 item Ground Arabika (dari roasted
 * curah Medium) per grind size populer: Medium, Fine, Extra Fine. Kategori
 * RTB (sudah ada sejak Fase B). Satuan kg, tipe bahan_baku, stok awal 0 di
 * RST001.
 */
class ItemGroundSeeder extends Seeder
{
    public function run(): void
    {
        $cat = ItemCategory::where('kode_kategori', 'RTB')->value('id');
        $rst = Cabang::where('kode_cabang', 'RST001')->value('id');

        foreach ([
            'GRD-ARB-MEDIUM-M' => 'Ground Arabika Medium Roast — Grind Medium',
            'GRD-ARB-MEDIUM-F' => 'Ground Arabika Medium Roast — Grind Fine',
            'GRD-ARB-MEDIUM-XF' => 'Ground Arabika Medium Roast — Grind Extra Fine',
        ] as $kode => $nama) {
            $item = Item::updateOrCreate(
                ['kode_item' => $kode],
                [
                    'nama_item'        => $nama,
                    'item_category_id' => $cat,
                    'tipe'             => 'bahan_baku',
                    'satuan'           => 'kg',
                    'harga_jual'       => 0,
                    'qty_minimum'      => 0,
                    'is_active'        => true,
                ]
            );

            if ($rst && ! Stock::where('item_id', $item->id)->where('lokasi_id', $rst)->exists()) {
                Stock::create(['item_id' => $item->id, 'lokasi_id' => $rst, 'qty' => 0]);
            }
        }

        $this->command?->info('ItemGroundSeeder: 3 item Ground Arabika (Medium/Fine/ExtraFine) di-seed (idempotent).');
    }
}
