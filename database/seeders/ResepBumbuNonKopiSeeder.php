<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsResepMenu;
use Illuminate\Database\Seeder;

/**
 * Resep 4 menu non-kopi (Isu #3, 2026-09-24). Air Mineral sengaja tanpa
 * resep (produk botol jadi, stok dipotong langsung). Teh celup belum ada
 * di inventory → dilewati (Es Teh Manis/Teh Tarik tanpa bahan teh).
 */
class ResepBumbuNonKopiSeeder extends Seeder
{
    use SeedsResepMenu;

    public function run(): void
    {
        $cup12 = [['KMS-001', 1, 'pcs'], ['KMS-003', 1, 'pcs']];
        $cup16 = [['KMS-002', 1, 'pcs'], ['KMS-003', 1, 'pcs']];

        $resep = [
            'Es Teh Manis'  => [['BHN-004', 20, 'g'], ...$cup12],
            'Teh Tarik'     => [['BHN-001', 100, 'ml'], ['BHN-004', 20, 'g'], ...$cup12],
            'Cokelat Panas' => [['BHN-007', 25, 'g'], ['BHN-001', 200, 'ml'], ['BHN-004', 10, 'g'], ...$cup16],
            'Matcha Latte'  => [['BHN-008', 15, 'g'], ['BHN-001', 200, 'ml'], ['BHN-004', 10, 'g'], ...$cup16],
        ];

        [$baru, $baris] = $this->seedResepMenu($resep, 'Resep menu non-kopi (Owner, 2026-09-24)');
        $this->command?->info("ResepBumbuNonKopiSeeder: {$baru} resep baru, {$baris} baris bahan ditambahkan.");
    }
}
