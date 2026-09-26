<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Roastery V2 Minggu 2-3 (2026-09-27) — kategori "BHK" (Buah Kopi Cherry) +
 * 2 item cherry basah (Arabika/Robusta Sidikalang, satuan kg). Tipe
 * bahan_baku, stok awal 0 di RST001 (Gudang Roastery) — bertambah lewat
 * modul Beli Buah Kopi saat status "diterima".
 */
class ItemCherrySeeder extends Seeder
{
    public function run(): void
    {
        $cat = ItemCategory::updateOrCreate(
            ['kode_kategori' => 'BHK'],
            ['nama_kategori' => 'Buah Kopi Cherry', 'deskripsi' => 'Buah kopi basah dari petani, sebelum processing jadi green bean']
        );

        $rst = Cabang::where('kode_cabang', 'RST001')->value('id');

        foreach ([
            'BHK-ARABIKA-SDK' => 'Buah Arabika Sidikalang',
            'BHK-ROBUSTA-SDK' => 'Buah Robusta Sidikalang',
        ] as $kode => $nama) {
            $item = Item::updateOrCreate(
                ['kode_item' => $kode],
                [
                    'nama_item'        => $nama,
                    'item_category_id' => $cat->id,
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

        $this->command?->info('ItemCherrySeeder: kategori BHK + 2 item cherry di-seed (idempotent).');
    }
}
