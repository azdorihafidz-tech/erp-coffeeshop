<?php

namespace Database\Seeders;

use App\Models\Petani;
use Illuminate\Database\Seeder;

/**
 * Roastery V2 Minggu 1-2 (2026-09-26) — 3 petani contoh untuk testing
 * modul Beli Buah Kopi & Processing (Minggu 2-4).
 */
class PetaniSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'kode_petani' => 'PTN-001',
                'nama'        => 'Pak Budi',
                'telepon'     => '0812-1111-0001',
                'alamat'      => 'Sidikalang, Kabupaten Dairi, Sumatera Utara',
                'nama_kebun'  => 'Kebun Sidikalang Atas',
                'is_active'   => true,
            ],
            [
                'kode_petani' => 'PTN-002',
                'nama'        => 'Pak Ali',
                'telepon'     => '0812-2222-0002',
                'alamat'      => 'Merek, Kabupaten Karo, Sumatera Utara',
                'nama_kebun'  => 'Kebun Merek Lereng',
                'is_active'   => true,
            ],
            [
                'kode_petani' => 'PTN-003',
                'nama'        => 'Pak Chandra',
                'telepon'     => '0812-3333-0003',
                'alamat'      => 'Kabanjahe, Kabupaten Karo, Sumatera Utara',
                'nama_kebun'  => 'Kebun Kabanjahe Baru',
                'is_active'   => true,
            ],
        ];

        foreach ($data as $row) {
            Petani::updateOrCreate(['kode_petani' => $row['kode_petani']], $row);
        }

        $this->command?->info('PetaniSeeder: 3 petani contoh di-seed (idempotent).');
    }
}
