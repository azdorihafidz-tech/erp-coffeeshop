<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Stock;
use Illuminate\Database\Seeder;

/**
 * Roastery V2 Minggu 5-6 (2026-09-30) — 12 item pack retail (kategori RTB
 * existing): 4 varian dasar (Whole Medium Roast, Ground Medium, Ground Fine,
 * Ground Extra Fine) x 3 ukuran (250g/500g/1kg). Tipe produk_jual (SIAP
 * DIJUAL — beda dari curah/ground bahan_baku Minggu 3-5), harga_jual
 * placeholder wajar untuk retail, stok awal 0 di RST001.
 *
 * CATATAN: menu awal minta SKU "Arabika Washed Medium" — tapi curah/ground
 * yang genuinely ada di sistem berbasis ROAST LEVEL (Medium/Fine/ExtraFine
 * grind, dari RTB-CURAH-ARABIKA-MEDIUM), bukan processing method (Washed).
 * Dipakai sumber yang benar-benar tersedia (curah + 3 ground) supaya
 * packing batch tidak macet karena item sumber tidak ada.
 */
class ItemPackSeeder extends Seeder
{
    private const HARGA = ['250g' => 45000, '500g' => 85000, '1kg' => 160000];

    public function run(): void
    {
        $cat = ItemCategory::where('kode_kategori', 'RTB')->value('id');
        $rst = Cabang::where('kode_cabang', 'RST001')->value('id');

        $varian = [
            'WHOLE-MEDIUM'  => 'Kopi Arabika Medium Roast — Whole Bean',
            'GROUND-MEDIUM' => 'Kopi Arabika Medium Roast — Ground Medium',
            'GROUND-FINE'   => 'Kopi Arabika Medium Roast — Ground Fine',
            'GROUND-XFINE'  => 'Kopi Arabika Medium Roast — Ground Extra Fine',
        ];

        $count = 0;
        foreach ($varian as $kodeVarian => $namaVarian) {
            foreach (self::HARGA as $ukuran => $harga) {
                $kode = "RTBPACK-{$kodeVarian}-{$ukuran}";
                $item = Item::updateOrCreate(
                    ['kode_item' => $kode],
                    [
                        'nama_item'        => "{$namaVarian} {$ukuran}",
                        'item_category_id' => $cat,
                        'tipe'             => 'produk_jual',
                        'satuan'           => 'pack',
                        'harga_jual'       => $harga,
                        'qty_minimum'      => 0,
                        'is_active'        => true,
                    ]
                );

                if ($rst && ! Stock::where('item_id', $item->id)->where('lokasi_id', $rst)->exists()) {
                    Stock::create(['item_id' => $item->id, 'lokasi_id' => $rst, 'qty' => 0]);
                }
                $count++;
            }
        }

        $this->command?->info("ItemPackSeeder: {$count} item pack retail (4 varian x 3 ukuran) di-seed (idempotent).");
    }
}
