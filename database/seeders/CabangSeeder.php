<?php

namespace Database\Seeders;

use App\Models\Cabang;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    /**
     * Struktur D'mentai (Tahap 2 - Master Data, 2026-09-13): 1 Gudang Pusat
     * (rangkap HO, sesuai CLAUDE.md 1.3) + 5 Outlet retail.
     *
     * 3 baris pertama (id 1,2,3) adalah cabang WARISAN dari Berkah Mulyo yang
     * di-RENAME (bukan dihapus+buat-ulang) — karyawan/kas/shift existing sudah
     * terhubung ke id tersebut, jadi di-update via updateOrCreate(['id'=>X])
     * supaya idempotent tanpa merusak relasi yang sudah ada. 3 baris berikut
     * (Outlet 3-5) baru, di-match via kode_cabang.
     */
    public function run(): void
    {
        $byId = [
            1 => [
                'nama_cabang'        => 'Kopi Drip Pusat',
                'kode_cabang'        => 'GP001',
                'alamat'             => 'TBD',
                'telepon'            => '021-1234567',
                'tipe'               => 'gudang_pusat',
                'is_active'          => true,
                'latitude'           => -6.1754,
                'longitude'          => 106.8272,
                'radius_absen_meter' => 150,
                'jam_masuk'          => '07:30',
            ],
            2 => [
                'nama_cabang'        => 'Outlet 1',
                'kode_cabang'        => 'OUT001',
                'alamat'             => 'Jl. Raya No. 10, Kota',
                'telepon'            => '021-2345678',
                'tipe'               => 'cabang',
                'is_active'          => true,
                'latitude'           => -6.2446,
                'longitude'          => 106.7986,
                'radius_absen_meter' => 100,
                'jam_masuk'          => '08:00',
            ],
            3 => [
                'nama_cabang'        => 'Outlet 2',
                'kode_cabang'        => 'OUT002',
                'alamat'             => 'Jl. Timur No. 20, Kota',
                'telepon'            => '021-3456789',
                'tipe'               => 'cabang',
                'is_active'          => true,
                'latitude'           => -6.2166,
                'longitude'          => 106.8663,
                'radius_absen_meter' => 100,
                'jam_masuk'          => '08:00',
            ],
        ];

        foreach ($byId as $id => $data) {
            Cabang::updateOrCreate(['id' => $id], $data);
        }

        $outletBaru = [
            [
                'nama_cabang'        => 'Outlet 3',
                'kode_cabang'        => 'OUT003',
                'alamat'             => 'Jl. Barat No. 30, Kota',
                'telepon'            => '021-4567890',
                'tipe'               => 'cabang',
                'is_active'          => true,
                'latitude'           => -6.1701,
                'longitude'          => 106.7909,
                'radius_absen_meter' => 100,
                'jam_masuk'          => '08:00',
            ],
            [
                'nama_cabang'        => 'Outlet 4',
                'kode_cabang'        => 'OUT004',
                'alamat'             => 'Jl. Selatan No. 40, Kota',
                'telepon'            => '021-5678901',
                'tipe'               => 'cabang',
                'is_active'          => true,
                'latitude'           => -6.2897,
                'longitude'          => 106.7995,
                'radius_absen_meter' => 100,
                'jam_masuk'          => '08:00',
            ],
            [
                'nama_cabang'        => 'Outlet 5',
                'kode_cabang'        => 'OUT005',
                'alamat'             => 'Jl. Utara No. 50, Kota',
                'telepon'            => '021-6789012',
                'tipe'               => 'cabang',
                'is_active'          => true,
                'latitude'           => -6.1352,
                'longitude'          => 106.8133,
                'radius_absen_meter' => 100,
                'jam_masuk'          => '08:00',
            ],
        ];

        foreach ($outletBaru as $data) {
            Cabang::updateOrCreate(['kode_cabang' => $data['kode_cabang']], $data);
        }

        // Roastery V2 Minggu 1-2 (2026-09-26) — unit bisnis roastery terpisah
        // dari GP001 (yang tetap "gudang_pusat" murni distribusi). RST001 =
        // lokasi processing/roasting fisik di rumah Owner, akan jadi kanal
        // jual sendiri (retail walk-in + wholesale) di Modul C nanti.
        Cabang::updateOrCreate(['kode_cabang' => 'RST001'], [
            'nama_cabang'        => 'Gudang Roastery Kopi Drip',
            'kode_cabang'        => 'RST001',
            'alamat'             => 'TBD (rumah Owner)',
            'telepon'            => null,
            'tipe'               => 'roastery',
            'is_active'          => true,
            'latitude'           => null,
            'longitude'          => null,
            'radius_absen_meter' => 150,
            'jam_masuk'          => '07:00',
            // Roastery V2 Minggu 6-7 — POS Roastery fokus retail/wholesale/internal via
            // Takeaway (Q2). Dine-in & Frozen tidak relevan (tidak ada dine-in/meja di unit roastery).
            'dine_in_aktif'      => false,
            'takeaway_aktif'     => true,
            'frozen_aktif'       => false,
        ]);
    }
}
