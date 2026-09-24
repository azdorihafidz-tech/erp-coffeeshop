<?php

namespace Database\Seeders\Concerns;

use App\Models\Item;
use App\Models\ResepBumbu;

/**
 * Helper resep menu (Roastery R4 + Isu #3). Idempotent & non-destruktif:
 * membuat resep kalau belum ada, lalu MENAMBAH baris bahan yang belum ada
 * (dicek per item_id). Baris yang sudah ada (mis. diedit Owner lewat UI)
 * tidak pernah diubah. Catatan: baris yang sengaja dihapus Owner akan
 * ditambahkan lagi kalau seeder dijalankan ulang.
 *
 * Satuan baris resep: g/gram/ml dibagi 1000, 'ons' dibagi 10, lainnya
 * dipakai apa adanya (lihat ResepBumbuItem::getQtyPerUnitDalamKgAttribute) —
 * jadi 'ml' utk item bersatuan liter, 'g' utk item bersatuan kg, 'pcs'
 * utk item pcs, dan fraksi 'botol' utk sirup (1 botol = 750 ml).
 */
trait SeedsResepMenu
{
    /** @param array<string, array<int, array{0:string,1:float|int,2:string}>> $resepPerMenu nama_item => [[kode_bahan, qty, satuan], ...] */
    protected function seedResepMenu(array $resepPerMenu, string $catatan): array
    {
        $resepBaru = 0;
        $barisBaru = 0;
        $bahan = Item::whereIn('kode_item', collect($resepPerMenu)->flatten(1)->pluck(0)->unique()->all())
            ->get()->keyBy('kode_item');

        foreach ($resepPerMenu as $namaMenu => $baris) {
            $menu = Item::where('nama_item', $namaMenu)->where('tipe', 'produk_jual')->first();
            if (! $menu) {
                $this->command?->warn("Menu '{$namaMenu}' tidak ditemukan, di-skip.");
                continue;
            }

            $resep = ResepBumbu::where('item_id', $menu->id)->first();
            if (! $resep) {
                $resep = ResepBumbu::create([
                    'nama' => $menu->nama_item, 'kode' => 'RESEP-' . $menu->kode_item,
                    'item_id' => $menu->id, 'is_active' => true, 'catatan' => $catatan,
                ]);
                $resepBaru++;
            }

            foreach ($baris as [$kode, $qty, $satuan]) {
                $item = $bahan[$kode] ?? null;
                if (! $item) {
                    $this->command?->warn("Bahan {$kode} tidak ditemukan (menu {$namaMenu}), baris di-skip.");
                    continue;
                }
                if ($resep->items()->where('item_id', $item->id)->exists()) {
                    continue;
                }
                $resep->items()->create([
                    'item_id' => $item->id, 'qty_per_unit' => $qty, 'satuan' => $satuan,
                    'is_wajib' => true, 'mode_harga' => 'gratis',
                    'urutan' => ((int) $resep->items()->max('urutan')) + 1,
                ]);
                $barisBaru++;
            }
        }

        return [$resepBaru, $barisBaru];
    }
}
