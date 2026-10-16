<?php

namespace App\Http\Controllers;

use App\Enums\RoleUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Go-Live Reset Controller (2026-10-16)
 * =====================================
 * Hapus TOTAL data production + re-seed master data saja — supaya siap
 * go-live dari state bersih (0 transaksi, 0 stok, master lengkap).
 *
 * Keamanan:
 * - Protected by `auth` middleware (user wajib login dulu lewat /login)
 * - Role check INLINE: hanya role `owner` yang boleh akses (bukan admin
 *   pusat / manajer / dll, biar risiko salah klik rendah)
 * - POST butuh phrase konfirmasi eksplisit ("HAPUS SEMUA DATA PRODUCTION")
 *
 * Instruksi pakai di cPanel production:
 * 1. Login via /login sebagai akun owner (admin@kopidrip.com)
 * 2. Akses /go-live-reset di browser
 * 3. Baca peringatan → ketik phrase konfirmasi → klik tombol merah
 * 4. Tunggu sampai hasil muncul (migrate:fresh biasanya 1-3 menit)
 * 5. SETELAH SELESAI: hapus route go-live-reset dari routes/web.php
 *    + controller ini + push lagi (atau biarkan — tapi re-trigger butuh
 *    role owner lagi, risiko kecil)
 */
class GoLiveResetController extends Controller
{
    private const CONFIRM_PHRASE = 'HAPUS SEMUA DATA PRODUCTION';

    private function assertOwner(): void
    {
        $user = auth()->user();
        $role = $user?->role;
        $roleValue = $role instanceof RoleUser ? $role->value : (string) $role;

        if ($roleValue !== RoleUser::Owner->value) {
            abort(403, 'Hanya Owner yang boleh menjalankan reset production.');
        }
    }

    public function form()
    {
        $this->assertOwner();
        return view('admin.go-live-reset', [
            'confirmPhrase' => self::CONFIRM_PHRASE,
        ]);
    }

    public function execute(Request $request)
    {
        $this->assertOwner();

        $request->validate([
            'confirm_phrase' => 'required|string',
        ]);

        if ($request->input('confirm_phrase') !== self::CONFIRM_PHRASE) {
            return back()->with('error', 'Phrase konfirmasi tidak sesuai. Ketik PERSIS: ' . self::CONFIRM_PHRASE);
        }

        set_time_limit(300);
        $logs = [];
        $startTime = microtime(true);

        $log = function (string $msg, string $type = 'info') use (&$logs) {
            $logs[] = ['type' => $type, 'ts' => date('H:i:s'), 'msg' => $msg];
        };

        try {
            $log('Step 1/3: migrate:fresh --force (drop + re-create all tables)');
            Artisan::call('migrate:fresh', ['--force' => true]);
            $log(trim(Artisan::output()), 'ok');

            $log('Step 2/3: db:seed ProductionSeeder (master data only)');
            Artisan::call('db:seed', [
                '--class' => 'ProductionSeeder',
                '--force' => true,
            ]);
            $log(trim(Artisan::output()), 'ok');

            $log('Step 3/3: clear cache (config/view/route/cache)');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            Artisan::call('cache:clear');
            $log('All caches cleared.', 'ok');

            $elapsed = round(microtime(true) - $startTime, 2);
            $log("");
            $log("RESET BERHASIL ({$elapsed} detik).", 'ok');
            $log("");
            $log("LANGKAH SELANJUTNYA:");
            $log("1. Logout — session lama invalid karena tabel sessions sudah di-drop & re-create.");
            $log("2. Login ulang: admin@kopidrip.com / password");
            $log("3. GANTI PASSWORD semua akun default (menu User).");
            $log("4. Isi saldo awal Kas kalau outlet butuh modal (menu Keuangan - Kas).");
            $log("5. Siap pakai. Selamat go-live!");
        } catch (\Throwable $e) {
            $log("GAGAL: " . $e->getMessage(), 'err');
            $log("File: " . $e->getFile() . ':' . $e->getLine(), 'err');
            $log("");
            $log("1. Restore backup .sql via cPanel - phpMyAdmin - Import.", 'err');
            $log("2. Hubungi developer dengan screenshot error ini.", 'err');

            try {
                Log::error('Go-live reset failed', ['exception' => $e]);
            } catch (\Throwable $ignored) {
                // ignore
            }
        }

        try {
            $logPath = storage_path('logs/go-live-reset.log');
            $plain = array_map(fn ($l) => "[{$l['ts']}] [{$l['type']}] {$l['msg']}", $logs);
            @file_put_contents($logPath, implode("\n", $plain) . "\n", FILE_APPEND);
        } catch (\Throwable $ignored) {
            // ignore file write error
        }

        return view('admin.go-live-reset-result', ['logs' => $logs]);
    }
}
