<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $gudangPusat = Cabang::where('kode_cabang', 'GP001')->first();
        $cabangA = Cabang::where('kode_cabang', 'OUT001')->first();
        $cabangB = Cabang::where('kode_cabang', 'OUT002')->first();

        $users = [
            // Owner — akses semua cabang
            [
                'name'      => 'Admin Owner',
                'email'     => 'admin@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'owner',
                'telepon'   => '081234567890',
                'is_active' => true,
                'cabangs'   => [
                    $gudangPusat?->id => ['is_default' => true],
                    $cabangA?->id     => ['is_default' => false],
                    $cabangB?->id     => ['is_default' => false],
                ],
            ],
            // Admin Pusat
            [
                'name'      => 'Admin Pusat',
                'email'     => 'adminpusat@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'admin_pusat',
                'telepon'   => '081234567801',
                'is_active' => true,
                'cabangs'   => [
                    $gudangPusat?->id => ['is_default' => true],
                    $cabangA?->id     => ['is_default' => false],
                    $cabangB?->id     => ['is_default' => false],
                ],
            ],
            // Admin Gudang Pusat / Roastery
            [
                'name'      => 'Admin Gudang / Roastery',
                'email'     => 'gudang@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'admin_gudang',
                'telepon'   => '081234567893',
                'is_active' => true,
                'cabangs'   => [
                    $gudangPusat?->id => ['is_default' => true],
                ],
            ],
            // Manajer Outlet 1
            [
                'name'      => 'Manajer Outlet 1 (TBD)',
                'email'     => 'manajer.o1@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'manajer_cabang',
                'telepon'   => '081234567891',
                'is_active' => true,
                'cabangs'   => [
                    $cabangA?->id => ['is_default' => true],
                ],
            ],
            // Manajer Outlet 2
            [
                'name'      => 'Manajer Outlet 2 (TBD)',
                'email'     => 'manajer.o2@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'manajer_cabang',
                'telepon'   => '081234567895',
                'is_active' => true,
                'cabangs'   => [
                    $cabangB?->id => ['is_default' => true],
                ],
            ],
            // Kasir Outlet 1
            [
                'name'      => 'Kasir Outlet 1 (TBD)',
                'email'     => 'kasir.o1@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'kasir',
                'telepon'   => '081234567892',
                'is_active' => true,
                'cabangs'   => [
                    $cabangA?->id => ['is_default' => true],
                ],
            ],
            // Kasir Outlet 2
            [
                'name'      => 'Kasir Outlet 2 (TBD)',
                'email'     => 'kasir.o2@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'kasir',
                'telepon'   => '081234567896',
                'is_active' => true,
                'cabangs'   => [
                    $cabangB?->id => ['is_default' => true],
                ],
            ],
            // Barista (Operator Produksi) Outlet 1
            [
                'name'      => 'Barista Outlet 1 (TBD)',
                'email'     => 'barista.o1@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'operator_produksi',
                'telepon'   => '081234567894',
                'is_active' => true,
                'cabangs'   => [
                    $cabangA?->id => ['is_default' => true],
                ],
            ],
            // Barista (Operator Produksi) Outlet 2
            [
                'name'      => 'Barista Outlet 2 (TBD)',
                'email'     => 'barista.o2@kopidrip.com',
                'password'  => Hash::make('password'),
                'role'      => 'operator_produksi',
                'telepon'   => '081234567897',
                'is_active' => true,
                'cabangs'   => [
                    $cabangB?->id => ['is_default' => true],
                ],
            ],
        ];

        foreach ($users as $userData) {
            $cabangs = $userData['cabangs'];
            unset($userData['cabangs']);

            $user = User::create(array_merge($userData, ['email_verified_at' => now()]));

            // Assign cabang — filter null keys
            $validCabangs = array_filter($cabangs, fn($id) => !is_null($id), ARRAY_FILTER_USE_KEY);
            if (!empty($validCabangs)) {
                $user->cabangs()->attach($validCabangs);
            }
        }
    }
}
