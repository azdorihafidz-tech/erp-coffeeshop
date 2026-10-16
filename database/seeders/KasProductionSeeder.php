<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Kas;
use Illuminate\Database\Seeder;

/**
 * Go-Live Reset (2026-10-16) — variant KasSeeder untuk production.
 * Sama struktur dengan KasSeeder (3 metode: Tunai/Transfer/QRIS per outlet
 * + RST001 roastery), TAPI saldo awal 0 semua. Owner isi saldo real lewat
 * UI Keuangan setelah go-live.
 *
 * Tabel `kas` tetap butuh entry per cabang karena closeBill()/split-payment
 * reference Kas berdasarkan `default_untuk` enum.
 */
class KasProductionSeeder extends Seeder
{
    private const METODE = [
        'tunai'    => ['label' => 'Kas Tunai',    'tipe_kas' => 'tunai'],
        'transfer' => ['label' => 'Kas Transfer', 'tipe_kas' => 'bank'],
        'qris'     => ['label' => 'Kas QRIS',     'tipe_kas' => 'bank'],
    ];

    public function run(): void
    {
        $cabangs = Cabang::whereIn('kode_cabang', ['OUT001', 'OUT002', 'OUT003', 'OUT004', 'OUT005', 'RST001'])->get();

        if ($cabangs->isEmpty()) {
            $this->command?->warn('KasProductionSeeder: tidak ada outlet/roastery yang match, di-skip.');
            return;
        }

        foreach ($cabangs as $cabang) {
            foreach (self::METODE as $key => $meta) {
                Kas::updateOrCreate(
                    [
                        'cabang_id'     => $cabang->id,
                        'default_untuk' => $key,
                    ],
                    [
                        'nama_kas'       => "{$meta['label']} - {$cabang->nama_cabang}",
                        'tipe_kas'       => $meta['tipe_kas'],
                        'saldo_awal'     => 0,
                        'saldo_sekarang' => 0,
                        'is_active'      => true,
                    ]
                );
            }
        }

        $this->command?->info('KasProductionSeeder: ' . ($cabangs->count() * 3) . ' kas dibuat dengan saldo awal Rp0.');
    }
}
