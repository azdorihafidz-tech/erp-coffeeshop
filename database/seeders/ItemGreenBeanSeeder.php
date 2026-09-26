<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Roastery V2 Minggu 3-4 (2026-09-27) — 4 item Green Bean Arabika Sidikalang,
 * satu per processing method (Washed/Natural/Honey/Wine). Kategori GRB sudah
 * ada sejak Fase B (item generik GRB-001/002) — seeder ini cuma menambah,
 * TIDAK mengubah item lama. Satuan kg, tipe bahan_baku, stok awal 0 di
 * RST001. Cost per kg TIDAK diinput manual — dihitung otomatis oleh
 * ProcessingBatchService dari cherry yang dipakai (lihat 4.28).
 */
class ItemGreenBeanSeeder extends Seeder
{
    public function run(): void
    {
        $cat = ItemCategory::where('kode_kategori', 'GRB')->value('id');
        $rst = Cabang::where('kode_cabang', 'RST001')->value('id');

        foreach ([
            'GRB-ARB-WASHED'  => 'Green Arabika Sidikalang Washed',
            'GRB-ARB-NATURAL' => 'Green Arabika Sidikalang Natural',
            'GRB-ARB-HONEY'   => 'Green Arabika Sidikalang Honey',
            'GRB-ARB-WINE'    => 'Green Arabika Sidikalang Wine',
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

        $this->command?->info('ItemGreenBeanSeeder: 4 item Green Bean Arabika (per method) di-seed (idempotent).');
    }
}
