<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\CherryPurchase;
use App\Models\Item;
use App\Models\Petani;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Roastery V2 Minggu 2-3 (2026-09-27) — 2 contoh transaksi Beli Buah Kopi
 * berstatus draft, supaya UI langsung ada data saat pertama dibuka.
 */
class CherryPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $rst = Cabang::where('kode_cabang', 'RST001')->value('id');
        $owner = User::where('email', 'admin@kopidrip.com')->value('id');
        $petaniBudi = Petani::where('kode_petani', 'PTN-001')->value('id');
        $petaniAli  = Petani::where('kode_petani', 'PTN-002')->value('id');
        $arabika  = Item::where('kode_item', 'BHK-ARABIKA-SDK')->value('id');
        $robusta  = Item::where('kode_item', 'BHK-ROBUSTA-SDK')->value('id');

        if (! $rst || ! $owner || ! $petaniBudi || ! $arabika) {
            $this->command?->warn('CherryPurchaseSeeder: data prasyarat belum lengkap (RST001/user/petani/item), di-skip.');
            return;
        }

        $contoh = [
            [
                'kode_transaksi' => 'BC-' . now()->format('Ym') . '-0001',
                'tanggal'        => now()->toDateString(),
                'petani_id'      => $petaniBudi,
                'cherry_item_id' => $arabika,
                'jenis_buah'     => 'arabika',
                'qty_kg'         => 50,
                'harga_per_kg'   => 12000,
                'kualitas_grade' => 'A',
                'catatan'        => 'Panen pagi, petik merah',
            ],
            [
                'kode_transaksi' => 'BC-' . now()->format('Ym') . '-0002',
                'tanggal'        => now()->toDateString(),
                'petani_id'      => $petaniAli ?? $petaniBudi,
                'cherry_item_id' => $robusta ?? $arabika,
                'jenis_buah'     => 'robusta',
                'qty_kg'         => 30,
                'harga_per_kg'   => 8000,
                'kualitas_grade' => 'B',
                'catatan'        => null,
            ],
        ];

        foreach ($contoh as $row) {
            CherryPurchase::updateOrCreate(
                ['kode_transaksi' => $row['kode_transaksi']],
                $row + ['cabang_id' => $rst, 'status' => 'draft', 'user_id' => $owner]
            );
        }

        $this->command?->info('CherryPurchaseSeeder: 2 transaksi Beli Buah Kopi contoh (draft) di-seed (idempotent).');
    }
}
