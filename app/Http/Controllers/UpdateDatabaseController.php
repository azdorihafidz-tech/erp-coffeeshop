<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use App\Models\Permission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Update Database Production (2026-10-19)
 * =======================================
 * Untuk cPanel tanpa SSH: jalankan migration baru + seeder konten setelah
 * kode baru di-upload, TANPA menghapus data. Beda total dengan /go-live-reset
 * (yang mengosongkan database).
 *
 * Yang dijalankan:
 * 1. migrate --force (hanya migration yang belum pernah jalan)
 * 2. PermissionSeeder, PanduanKontenSeeder, TooltipKontenSeeder (idempotent)
 * 3. Tambah mapping permission BARU ke role (PERMISSION_BARU) hanya kalau
 *    belum ada — TIDAK memakai RolePermissionSeeder (yang menghapus lalu
 *    mengisi ulang semua hak akses role ke default).
 * 4. Clear cache
 *
 * Owner-only (cek role inline, sama pola GoLiveResetController).
 * Sprint berikutnya yang menambah permission: tambahkan ke PERMISSION_BARU.
 */
class UpdateDatabaseController extends Controller
{
    /** permission => role yang mendapat akses bawaan (Owner selalu bypass). */
    private const PERMISSION_BARU = [
        // Sprint Closing Periode (4.39)
        'closing.view'        => ['admin_pusat', 'manajer_cabang'],
        'closing.create'      => ['admin_pusat', 'manajer_cabang'],
        'closing.finalize'    => ['admin_pusat'],
        'closing.konsolidasi' => ['admin_pusat'],
        'closing.export'      => ['admin_pusat', 'manajer_cabang'],
        // Sprint Label Cup (4.40)
        'label_cup.print'     => ['admin_pusat', 'manajer_cabang', 'kasir', 'operator_produksi'],
    ];

    private const SEEDERS = ['PermissionSeeder', 'PanduanKontenSeeder', 'TooltipKontenSeeder'];

    private function assertOwner(): void
    {
        $role = auth()->user()?->role;
        $roleValue = $role instanceof RoleUser ? $role->value : (string) $role;
        abort_if($roleValue !== RoleUser::Owner->value, 403, 'Hanya Owner yang boleh menjalankan update database.');
    }

    public function form()
    {
        $this->assertOwner();

        return view('admin.update-database', [
            'pending' => $this->migrationBelumJalan(),
            'seeders' => self::SEEDERS,
            'permissionBaru' => self::PERMISSION_BARU,
        ]);
    }

    public function execute()
    {
        $this->assertOwner();
        set_time_limit(300);

        $logs = [];
        $log = function (string $msg, string $type = 'info') use (&$logs) {
            $logs[] = ['type' => $type, 'ts' => date('H:i:s'), 'msg' => $msg];
        };

        try {
            $log('Step 1/4: migrate --force (hanya migration baru, data lama TIDAK dihapus)');
            Artisan::call('migrate', ['--force' => true]);
            $log(trim(Artisan::output()) ?: 'Tidak ada migration baru.', 'ok');

            $log('Step 2/4: seeder konten (idempotent)');
            foreach (self::SEEDERS as $seeder) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                $log("{$seeder} selesai.", 'ok');
            }

            $log('Step 3/4: tambah hak akses fitur baru ke role (yang sudah ada tidak diubah)');
            $permMap = Permission::pluck('id', 'name');
            $ditambah = 0;
            foreach (self::PERMISSION_BARU as $nama => $roles) {
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
            $log('UPDATE BERHASIL. Silakan buka menu yang baru untuk mengecek.', 'ok');
        } catch (\Throwable $e) {
            $log('GAGAL: ' . $e->getMessage(), 'err');
            $log('Data lama tidak terhapus. Screenshot halaman ini dan kirim ke developer.', 'err');
            try {
                Log::error('Update database failed', ['exception' => $e]);
            } catch (\Throwable) {
            }
        }

        return view('admin.update-database', [
            'pending' => $this->migrationBelumJalan(),
            'seeders' => self::SEEDERS,
            'permissionBaru' => self::PERMISSION_BARU,
            'logs' => $logs,
        ]);
    }

    /** Nama file migration yang belum pernah dijalankan. */
    private function migrationBelumJalan(): array
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
}
