<?php

namespace App\Support;

use App\Models\User;

/**
 * Cabang untuk laporan & akses data order (4.49).
 *
 * Owner/Admin Pusat (akses semua cabang) mengikuti cabang aktif di header
 * (null = "Semua Cabang"). Role cabang (Manajer, Kasir, dst) SELALU dibatasi
 * ke cabangnya sendiri: kalau sesinya menunjuk cabang yang bukan miliknya
 * (sesi basi, atau dipaksa lewat ?cabang_id=), dikembalikan ke cabang
 * default/pertamanya — tidak pernah null. -1 = user tidak punya cabang
 * (query apa pun hasilnya kosong).
 */
class CabangLaporan
{
    public static function id(?User $user = null): ?int
    {
        $user ??= auth()->user();
        $aktif = session('active_cabang_id');
        $aktif = $aktif ? (int) $aktif : null;

        if ($user->canAccessAllBranches()) {
            return $aktif;
        }
        if ($aktif && self::bolehAkses($aktif, $user)) {
            return $aktif;
        }

        return $user->defaultCabangId() ?? $user->cabangs()->value('cabangs.id') ?? -1;
    }

    /** User boleh melihat data cabang ini? */
    public static function bolehAkses(?int $cabangId, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if ($user->canAccessAllBranches()) {
            return true;
        }
        if (!$cabangId) {
            return false;
        }

        return $user->cabangs()->where('cabangs.id', $cabangId)->exists();
    }
}
