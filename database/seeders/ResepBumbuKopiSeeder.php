<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsResepMenu;
use Illuminate\Database\Seeder;

/**
 * Resep 12 menu kopi + manual brew (Roastery R4 → dilengkapi Isu #3,
 * 2026-09-24): biji (Roasted Curah Arabika Medium, takaran Owner Q5) + susu,
 * gula, sirup, cup & tutup. Idempotent, hanya MENAMBAH baris yang belum ada.
 *
 * Sirup (satuan botol): dicatat sbg fraksi botol — 15 ml / 750 ml = 0,02.
 * Jika Owner mengubah satuan sirup ke ml, ubah baris resep ini juga.
 */
class ResepBumbuKopiSeeder extends Seeder
{
    use SeedsResepMenu;

    private const BIJI = 'RTB-CURAH-ARABIKA-MEDIUM';
    private const SUSU = 'BHN-001', GULA = 'BHN-004', VANILLA = 'BHN-005', CARAMEL = 'BHN-006';
    private const CUP12 = 'KMS-001', CUP16 = 'KMS-002', TUTUP = 'KMS-003';

    public function run(): void
    {
        $biji  = fn (int $g) => [self::BIJI, $g, 'g'];
        $cup12 = [[self::CUP12, 1, 'pcs'], [self::TUTUP, 1, 'pcs']];
        $cup16 = [[self::CUP16, 1, 'pcs'], [self::TUTUP, 1, 'pcs']];
        $susu  = fn (int $ml) => [self::SUSU, $ml, 'ml'];
        $gula  = fn (int $g) => [self::GULA, $g, 'g'];

        $resep = [
            'Espresso'          => [$biji(9), ...$cup12],
            'Americano'         => [$biji(18), ...$cup12],
            'Kopi Susu'         => [$biji(18), $susu(100), $gula(15), ...$cup16],
            'Cappuccino'        => [$biji(18), $susu(150), ...$cup16],
            'Caffe Latte'       => [$biji(18), $susu(200), ...$cup16],
            'Vanilla Latte'     => [$biji(18), $susu(200), [self::VANILLA, 0.02, 'botol'], ...$cup16],
            'Caramel Macchiato' => [$biji(18), $susu(200), [self::CARAMEL, 0.02, 'botol'], ...$cup16],
            'Kopi Sidikalang'   => [$biji(20), $susu(100), $gula(10), ...$cup16],
            'V60'               => [$biji(18), ...$cup12],
            'Aeropress'         => [$biji(16), ...$cup12],
            'French Press'      => [$biji(20), ...$cup12],
            'Kopi Tubruk'       => [$biji(12), $gula(10), ...$cup12],
        ];

        [$baru, $baris] = $this->seedResepMenu($resep, 'Resep menu kopi (Owner, 2026-09-24)');
        $this->command?->info("ResepBumbuKopiSeeder: {$baru} resep baru, {$baris} baris bahan ditambahkan (yang sudah ada dilewati).");
    }
}
