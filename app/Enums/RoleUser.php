<?php

namespace App\Enums;

enum RoleUser: string
{
    case Owner = 'owner';
    case AdminPusat = 'admin_pusat';
    case AdminGudang = 'admin_gudang';
    case ManajerCabang = 'manajer_cabang';
    case Kasir = 'kasir';
    case OperatorProduksi = 'operator_produksi';
    case Helper = 'helper';
    /** Tim IT penyedia aplikasi — tersembunyi dari pelanggan (RANCANGAN_PORTING_DIMSUM.md Tahap A). */
    case Teknisi = 'teknisi';

    public function label(): string
    {
        return match($this) {
            RoleUser::Owner => 'Owner',
            RoleUser::AdminPusat => 'Admin Pusat',
            RoleUser::AdminGudang => 'Admin Gudang Pusat',
            RoleUser::ManajerCabang => 'Manajer Cabang',
            RoleUser::Kasir => 'Kasir',
            RoleUser::OperatorProduksi => 'Operator Produksi',
            RoleUser::Helper => 'Helper',
            RoleUser::Teknisi => 'Tim IT',
        };
    }

    public function canAccessAllBranches(): bool
    {
        return in_array($this, [self::Owner, self::AdminPusat, self::Teknisi]);
    }

    /**
     * Role yang boleh dipilih/dilihat pelanggan (semua kecuali Tim IT).
     * Dipakai Role & Hak Akses, form User, form Karyawan, dan pilihan role lain.
     *
     * @return array<int, RoleUser>
     */
    public static function untukPelanggan(bool $termasukOwner = true): array
    {
        return array_values(array_filter(self::cases(), fn (self $r) => $r !== self::Teknisi
            && ($termasukOwner || $r !== self::Owner)));
    }

    /** @return array<int, string> */
    public static function nilaiUntukPelanggan(bool $termasukOwner = true): array
    {
        return array_map(fn (self $r) => $r->value, self::untukPelanggan($termasukOwner));
    }
}
