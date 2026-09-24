<?php

namespace Database\Seeders;

use App\Models\JenisOlahan;
use Illuminate\Database\Seeder;

class JenisOlahanSeeder extends Seeder
{
    /**
     * Jenis olahan Kopi Drip: racikan minuman kopi, manual brew, batch
     * roasting biji, dapur makanan.
     */
    public function run(): void
    {
        $data = [
            ['nama' => 'Racikan Kopi',   'slug' => 'racikan-kopi',   'is_active' => true],
            ['nama' => 'Manual Brew',    'slug' => 'manual-brew',    'is_active' => true],
            ['nama' => 'Roasting Batch', 'slug' => 'roasting-batch', 'is_active' => true],
            ['nama' => 'Kitchen',        'slug' => 'kitchen',        'is_active' => true],
        ];

        foreach ($data as $row) {
            JenisOlahan::updateOrCreate(
                ['slug' => $row['slug']],
                $row
            );
        }
    }
}
