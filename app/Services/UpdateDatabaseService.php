<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Update database production tanpa hapus data (2026-10-19).
 * Dipakai halaman /update-database dan script public/deploy-*.php.
 *
 * Sprint berikutnya yang menambah permission WAJIB menambahkannya ke
 * PERMISSION_BARU (RolePermissionSeeder tidak dipakai di production
 * karena menghapus & mengisi ulang semua hak akses role).
 *
 * 4.49: role bawaan HANYA dicentangkan saat permission itu baru pertama kali
 * dibuat (belum ada di tabel permissions sebelum update). Permission yang
 * sudah ada tidak disentuh lagi — centangan yang dicabut Owner di Role & Hak
 * Akses tidak kembali tercentang di update berikutnya.
 */
class UpdateDatabaseService
{
    /** permission => role yang mendapat akses bawaan (Owner selalu bypass). */
    public const PERMISSION_BARU = [
        // Sprint Closing Periode (4.39)
        'closing.view'        => ['admin_pusat', 'manajer_cabang'],
        'closing.create'      => ['admin_pusat', 'manajer_cabang'],
        'closing.finalize'    => ['admin_pusat'],
        'closing.konsolidasi' => ['admin_pusat'],
        'closing.export'      => ['admin_pusat', 'manajer_cabang'],
        // Sprint Label Cup (4.40)
        'label_cup.print'     => ['admin_pusat', 'manajer_cabang', 'kasir', 'operator_produksi'],
        // Laporan di menu Penjualan (4.49) — role yang bisa membuka Riwayat Order
        'penjualan.laporan_transaksi.view' => ['admin_pusat', 'manajer_cabang', 'kasir', 'operator_produksi'],
        'penjualan.laporan_produk.view'    => ['admin_pusat', 'manajer_cabang', 'kasir', 'operator_produksi'],
        'penjualan.laporan_kategori.view'  => ['admin_pusat', 'manajer_cabang', 'kasir', 'operator_produksi'],
        'penjualan.laporan.keuntungan'     => ['admin_pusat', 'manajer_cabang'],
        // Produksi bahan setengah jadi di gudang (2026-11-06)
        'produksi.view'         => ['admin_pusat', 'admin_gudang', 'operator_produksi'],
        'produksi.create'       => ['admin_pusat', 'admin_gudang', 'operator_produksi'],
        'produksi.batalkan'     => ['admin_pusat', 'admin_gudang'],
        'produksi.resep.kelola' => ['admin_pusat', 'admin_gudang'],
        // Kiriman Cabang (2026-11-06)
        'kiriman.view'   => ['admin_pusat', 'admin_gudang', 'manajer_cabang', 'kasir'],
        'kiriman.terima' => ['admin_pusat', 'manajer_cabang', 'kasir'],
        // Retur Cabang (2026-11-06)
        'retur.view'   => ['admin_pusat', 'admin_gudang', 'manajer_cabang', 'kasir'],
        'retur.create' => ['admin_pusat', 'manajer_cabang', 'kasir'],
        'retur.terima' => ['admin_pusat', 'admin_gudang'],
    ];

    public const SEEDERS = ['PermissionSeeder', 'PanduanKontenSeeder', 'TooltipKontenSeeder'];

    /** Tabel yang jumlah barisnya ditampilkan sebelum/sesudah, bukti data tidak hilang. */
    public const TABEL_CEK = ['orders', 'order_items', 'transaksi_keuangans', 'stocks', 'kas', 'users', 'cabangs'];

    /** Rencana tanpa mengubah apa pun (tahap simulasi). */
    public function rencana(): array
    {
        $permMap = $this->permMap();
        $akanDitambah = [];
        foreach (self::PERMISSION_BARU as $nama => $roles) {
            if (isset($permMap[$nama])) {
                continue; // sudah ada → centangan diatur Owner, tidak disentuh
            }
            foreach ($roles as $role) {
                $akanDitambah[] = "{$nama} → {$role}";
            }
        }

        return [
            'migration'    => $this->migrationBelumJalan(),
            'seeders'      => self::SEEDERS,
            'hak_akses'    => $akanDitambah,
            'jumlah_data'  => $this->jumlahData(),
        ];
    }

    /** Jalankan update. Return log [['type' => ok|info|err, 'msg' => ...]]. */
    public function jalankan(): array
    {
        $logs = [];
        $log = function (string $msg, string $type = 'info') use (&$logs) {
            $logs[] = ['type' => $type, 'ts' => date('H:i:s'), 'msg' => $msg];
        };

        try {
            $log('Step 1/4: migrate --force (hanya migration baru, data lama TIDAK dihapus)');
            Artisan::call('migrate', ['--force' => true]);
            $log(trim(Artisan::output()) ?: 'Tidak ada migration baru.', 'ok');

            // Permission yang sudah ada SEBELUM seeder = sudah pernah dipasang
            // (centangannya milik Owner). Hanya yang baru dibuat yang dicentangkan.
            $sudahAda = $this->permMap();

            $log('Step 2/4: seeder konten (idempotent)');
            foreach (self::SEEDERS as $seeder) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                $log("{$seeder} selesai.", 'ok');
            }

            $log('Step 3/4: tambah hak akses fitur baru ke role (yang sudah ada tidak diubah)');
            $permMap = $this->permMap();
            $ditambah = 0;
            foreach (self::PERMISSION_BARU as $nama => $roles) {
                if (isset($sudahAda[$nama])) {
                    continue;
                }
                if (!isset($permMap[$nama])) {
                    $log("Permission {$nama} tidak ditemukan — dilewati.", 'err');
                    continue;
                }
                foreach ($roles as $role) {
                    $ada = DB::table('role_permissions')->where('role', $role)->where('permission_id', $permMap[$nama])->exists();
                    if (!$ada) {
                        DB::table('role_permissions')->insert(['role' => $role, 'permission_id' => $permMap[$nama]]);
                        $ditambah++;
                    }
                }
            }
            $log("{$ditambah} hak akses baru ditambahkan.", 'ok');

            $log('Step 4/4: clear cache');
            Cache::forget('all_permission_names');
            foreach (['config:clear', 'view:clear', 'route:clear', 'cache:clear'] as $cmd) {
                Artisan::call($cmd);
            }
            $log('Cache dibersihkan.', 'ok');
            $log('UPDATE BERHASIL.', 'ok');
        } catch (\Throwable $e) {
            $log('GAGAL: ' . $e->getMessage(), 'err');
            $log('Data lama tidak terhapus. Screenshot halaman ini dan kirim ke developer.', 'err');
            try {
                \Illuminate\Support\Facades\Log::error('Update database failed', ['exception' => $e]);
            } catch (\Throwable) {
            }
        }

        return $logs;
    }

    public function jumlahData(): array
    {
        $hasil = [];
        foreach (self::TABEL_CEK as $t) {
            try {
                $hasil[$t] = DB::table($t)->count();
            } catch (\Throwable) {
                $hasil[$t] = null;
            }
        }

        return $hasil;
    }

    public function migrationBelumJalan(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];

            return array_values(array_diff(array_keys($files), $ran));
        } catch (\Throwable) {
            return [];
        }
    }

    private function permMap()
    {
        return Permission::pluck('id', 'name');
    }
}
