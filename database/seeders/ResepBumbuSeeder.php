<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\JenisOlahan;
use App\Models\ResepBumbu;
use Illuminate\Database\Seeder;

/**
 * Tahap 4 D'mentai (2026-09-13) — resep produksi dimsum/gyoza, menggantikan
 * data Berkah Mulyo (Bakso Kojek/Kuah/Bakar, sudah dihapus di Tahap 2).
 *
 * Beda penting dari resep lama: setiap resep di sini terhubung LANGSUNG ke
 * 1 Item produk jadi (`resep_bumbu.item_id`, kolom baru Tahap 3) — supaya
 * POS bisa otomatis menemukan & memotong stok komposisinya tanpa kasir
 * pilih resep manual (beda dari alur jasa giling lama yang manual-pilih).
 *
 * Takaran (`qty_per_unit`, sudah di-rename dari `qty_per_kg`) sekarang
 * berarti "per 1 unit produksi" (1 porsi/pcs produk jadi), BUKAN lagi
 * "per 1 kg gilingan".
 */
class ResepBumbuSeeder extends Seeder
{
    public function run(): void
    {
        // Data Kopi Drip diisi Fase B
        $resepList = [];

        foreach ($resepList as $resepData) {
            $produkItem = Item::where('kode_item', $resepData['produk_item_kode'])->first();

            if (! $produkItem) {
                $this->command?->warn("  [{$resepData['nama']}] Produk '{$resepData['produk_item_kode']}' tidak ditemukan, di-skip.");
                continue;
            }

            $resep = ResepBumbu::updateOrCreate(
                ['kode' => $resepData['kode']],
                [
                    'nama'            => $resepData['nama'],
                    'item_id'         => $produkItem->id,
                    'jenis_olahan_id' => $resepData['jenis_olahan_id'],
                    'is_active'       => true,
                    'catatan'         => 'Data starting point dari seeder — silakan disesuaikan Owner.',
                ]
            );

            foreach ($resepData['bahan'] as $i => $bahan) {
                $item = Item::where('kode_item', $bahan['kode_item'])->first();

                if (! $item) {
                    $this->command?->warn("  [{$resepData['nama']}] Bahan '{$bahan['kode_item']}' tidak ditemukan di master, di-skip.");
                    continue;
                }

                $resep->items()->updateOrCreate(
                    ['item_id' => $item->id],
                    [
                        'qty_per_unit' => $bahan['qty_per_unit'],
                        'satuan'       => $bahan['satuan'],
                        'is_wajib'     => true,
                        'mode_harga'   => 'gratis',
                        'urutan'       => $i + 1,
                    ]
                );
            }
        }

        $this->command?->info('ResepBumbuSeeder selesai: 0 resep di-seed (data Kopi Drip menyusul Fase B).');
    }
}
