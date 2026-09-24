<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Meja;
use Illuminate\Database\Seeder;

/**
 * Data testing Fase E1 — 10 meja per outlet (OUT001-OUT005), Gudang Pusat
 * (GP001) TIDAK diberi meja (bukan tempat customer duduk, murni gudang +
 * roastery). Kapasitas & lokasi divariasikan biar realistis untuk testing
 * layout meja di E3 nanti.
 */
class MejaSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Cabang::whereIn('kode_cabang', ['OUT001', 'OUT002', 'OUT003', 'OUT004', 'OUT005'])->get();

        if ($outlets->count() !== 5) {
            $this->command?->warn('MejaSeeder: 5 outlet tidak lengkap, di-skip.');
            return;
        }

        // Kapasitas 2/4/6/8 diulang bergantian, lokasi: meja 9-10 outdoor, sisanya indoor
        $kapasitasCycle = [2, 4, 6, 8];

        foreach ($outlets as $cabang) {
            for ($nomor = 1; $nomor <= 10; $nomor++) {
                $kapasitas = $kapasitasCycle[($nomor - 1) % count($kapasitasCycle)];
                $lokasi    = $nomor > 8 ? 'outdoor' : 'indoor';

                $meja = Meja::where('cabang_id', $cabang->id)->where('nomor_meja', $nomor)->first();

                if ($meja) {
                    // Re-run seeder TIDAK boleh regenerate qr_token — sticker yang
                    // sudah dicetak harus tetap valid, cuma data non-QR yang di-sync.
                    $meja->update([
                        'nama_meja' => "Meja {$nomor}",
                        'kapasitas' => $kapasitas,
                        'lokasi'    => $lokasi,
                    ]);
                    continue;
                }

                Meja::create([
                    'cabang_id'  => $cabang->id,
                    'nomor_meja' => $nomor,
                    'nama_meja'  => "Meja {$nomor}",
                    'kapasitas'  => $kapasitas,
                    'lokasi'     => $lokasi,
                    'qr_token'   => Meja::generateQrToken(),
                    'qr_type'    => 'permanent',
                    'status'     => 'aktif',
                ]);
            }
        }

        Cabang::whereIn('kode_cabang', ['OUT001', 'OUT002', 'OUT003', 'OUT004', 'OUT005'])
            ->update(['qr_ordering_active' => true]);

        $this->command?->info('MejaSeeder selesai: 50 meja (5 outlet x 10) di-seed, qr_ordering_active aktif di 5 outlet.');
    }
}
