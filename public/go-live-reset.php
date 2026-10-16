<?php
/**
 * Go-Live Reset Script untuk ERP Kopi Drip (2026-10-16)
 * ======================================================
 * TUJUAN: hapus TOTAL data production + re-seed master data saja, supaya
 * siap go-live dari state bersih (0 transaksi, 0 stok, master lengkap).
 *
 * CARA PAKAI (di cPanel):
 * 1. SET env var `GOLIVE_RESET_TOKEN=<string random Anda>` di file `.env`
 *    production (minimal 20 karakter). Contoh: `GOLIVE_RESET_TOKEN=k8d4x9mNp2qR7vLs3H`.
 * 2. Upload file ini ke folder `public/` project (sudah ada di repo).
 * 3. Jalankan `php artisan config:clear` (atau akses helper clear-cache yang sudah ada)
 *    supaya .env baru ke-load.
 * 4. Akses: https://your-domain.com/go-live-reset.php?token=TOKEN_ANDA
 * 5. Baca peringatan di halaman, lalu ketik phrase konfirmasi persis.
 * 6. Klik tombol EKSEKUSI RESET → tunggu sampai selesai.
 * 7. SETELAH SELESAI: HAPUS file ini dari server + HAPUS baris `GOLIVE_RESET_TOKEN`
 *    dari `.env` supaya tidak bisa dijalankan ulang.
 *
 * YANG AKAN DILAKUKAN:
 * - `migrate:fresh --force` → DROP semua tabel + RE-CREATE dari migration
 * - `db:seed --class=ProductionSeeder --force` → isi master data saja
 *
 * YANG TIDAK DI-SEED (vs DatabaseSeeder dev):
 * - Supplier dummy, Karyawan dummy, Stok testing, Meja testing (50),
 *   Transaksi contoh (cherry/processing), Kas saldo 500rb → Kas saldo 0.
 *
 * AKUN LOGIN DEFAULT SETELAH RESET:
 * - admin@kopidrip.com / password
 * - manajer.pusat@kopidrip.com / password
 * - manajer.o1@kopidrip.com / password (dst untuk outlet 1-5)
 * - kasir.o1@kopidrip.com, barista.o1@kopidrip.com (dst)
 * ⚠️ WAJIB ganti password semua akun segera setelah login pertama!
 *
 * SAFETY:
 * - Script menolak eksekusi kalau token salah
 * - Butuh konfirmasi phrase EKSPLISIT ("HAPUS SEMUA DATA PRODUCTION")
 * - Butuh method POST (bukan cuma GET)
 * - Log hasil eksekusi ke `storage/logs/go-live-reset.log`
 */

define('CONFIRM_PHRASE', 'HAPUS SEMUA DATA PRODUCTION');

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Token WAJIB dari .env — bukan hardcode. Kalau env kosong, semua request ditolak.
$secretToken = env('GOLIVE_RESET_TOKEN', '');
$token = $_REQUEST['token'] ?? '';
if ($secretToken === '' || !hash_equals($secretToken, $token)) {
    http_response_code(403);
    echo "<h1>403 Forbidden</h1><p>Token tidak valid atau tidak diisi.</p>";
    echo "<p style='color:#888'>Pastikan sudah set <code>GOLIVE_RESET_TOKEN</code> di <code>.env</code> + jalankan <code>config:clear</code>.</p>";
    exit;
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$confirmPhrase = $_POST['confirm_phrase'] ?? '';

// === HALAMAN FORM (GET) ===
if (!$isPost) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Go-Live Reset — ERP Kopi Drip</title>
        <style>
            body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background:#f5f5f5; }
            .card { background:white; padding:30px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.08); }
            h1 { color:#c82333; margin-top:0; }
            .warning { background:#fff3cd; border-left:4px solid #ffc107; padding:15px; margin:15px 0; border-radius:4px; }
            .danger { background:#f8d7da; border-left:4px solid #dc3545; padding:15px; margin:15px 0; border-radius:4px; }
            ul { line-height:1.8; }
            input[type=text] { width:100%; padding:12px; font-size:16px; font-family:monospace; border:2px solid #ccc; border-radius:6px; margin:10px 0; }
            button { background:#dc3545; color:white; border:none; padding:14px 24px; font-size:16px; border-radius:6px; cursor:pointer; width:100%; font-weight:bold; }
            button:hover { background:#c82333; }
            code { background:#f0f0f0; padding:2px 6px; border-radius:3px; font-family:monospace; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⚠️ Go-Live Reset — ERP Kopi Drip</h1>

            <div class="danger">
                <strong>PERINGATAN: Tindakan ini TIDAK BISA DI-UNDO!</strong>
                <p>Semua data di database production akan DIHAPUS dan tabel di-create ulang dari nol.</p>
            </div>

            <h3>Yang akan dilakukan:</h3>
            <ul>
                <li><strong>DROP semua tabel</strong> di database production.</li>
                <li><strong>Re-create skema</strong> dari migration (fresh schema).</li>
                <li><strong>Seed master data saja</strong>: cabang, user, role, permission, item master, resep, panduan, tooltip, COA, dll.</li>
                <li><strong>TIDAK ikut</strong>: supplier dummy, karyawan dummy, stok testing, meja testing, transaksi contoh.</li>
                <li><strong>Kas</strong> dibuat dengan saldo awal Rp0 (silakan isi saldo real via menu Keuangan nanti).</li>
            </ul>

            <div class="warning">
                <strong>Pastikan sudah backup database!</strong>
                <p>Download backup .sql via cPanel → phpMyAdmin → Export sebelum lanjut.</p>
            </div>

            <h3>Setelah reset, akun login default:</h3>
            <ul>
                <li><code>admin@kopidrip.com</code> / <code>password</code> (Owner)</li>
                <li><code>manajer.pusat@kopidrip.com</code> / <code>password</code></li>
                <li><code>manajer.o1@kopidrip.com</code> s/d <code>o5</code> (per outlet)</li>
                <li><code>kasir.o1@kopidrip.com</code>, <code>barista.o1@kopidrip.com</code> (dst)</li>
            </ul>
            <p><strong>⚠️ WAJIB ganti password semua akun setelah login pertama.</strong></p>

            <hr>
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <p><strong>Untuk konfirmasi, ketik persis phrase berikut:</strong></p>
                <p><code><?= CONFIRM_PHRASE ?></code></p>
                <input type="text" name="confirm_phrase" placeholder="Ketik di sini..." autocomplete="off" required>
                <button type="submit">🔥 EKSEKUSI RESET PRODUCTION</button>
            </form>

            <p style="margin-top:20px; color:#888; font-size:13px;">
                Setelah reset selesai, HAPUS file <code>go-live-reset.php</code> dari server
                supaya tidak bisa dijalankan ulang oleh orang lain.
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// === EKSEKUSI (POST) ===
if ($confirmPhrase !== CONFIRM_PHRASE) {
    http_response_code(400);
    echo "<h1>400 Bad Request</h1><p>Phrase konfirmasi tidak sesuai. Kembali ke form sebelumnya dan ketik persis.</p>";
    exit;
}

// Jalankan Artisan commands
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset in Progress...</title>
    <style>
        body { font-family: monospace; max-width:900px; margin:20px auto; padding:20px; background:#1a1a1a; color:#0f0; }
        h1 { color:#fff; }
        pre { background:#000; padding:15px; border-radius:6px; white-space:pre-wrap; }
        .ok { color:#0f0; } .err { color:#f66; } .info { color:#6cf; }
    </style>
</head>
<body>
<h1>🔥 Executing Go-Live Reset...</h1>
<pre>
<?php
set_time_limit(300); // 5 menit, migrate+seed bisa lama
ob_implicit_flush(true);

$logs = [];
$startTime = microtime(true);

function log_msg($msg, $type = 'info', &$logs) {
    $cls = $type === 'ok' ? 'ok' : ($type === 'err' ? 'err' : 'info');
    $line = "[" . date('H:i:s') . "] " . $msg;
    echo "<span class='{$cls}'>" . htmlspecialchars($line) . "</span>\n";
    @ob_flush(); @flush();
    $logs[] = $line;
}

try {
    log_msg('Step 1/3: migrate:fresh --force (drop + re-create all tables)', 'info', $logs);
    Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
    log_msg(Illuminate\Support\Facades\Artisan::output(), 'ok', $logs);

    log_msg('Step 2/3: db:seed ProductionSeeder (master data only)', 'info', $logs);
    Illuminate\Support\Facades\Artisan::call('db:seed', [
        '--class' => 'ProductionSeeder',
        '--force' => true,
    ]);
    log_msg(Illuminate\Support\Facades\Artisan::output(), 'ok', $logs);

    log_msg('Step 3/3: clear cache (config/view/route/cache)', 'info', $logs);
    Illuminate\Support\Facades\Artisan::call('config:clear');
    Illuminate\Support\Facades\Artisan::call('view:clear');
    Illuminate\Support\Facades\Artisan::call('route:clear');
    Illuminate\Support\Facades\Artisan::call('cache:clear');
    log_msg('All caches cleared.', 'ok', $logs);

    $elapsed = round(microtime(true) - $startTime, 2);
    log_msg("", 'info', $logs);
    log_msg("✅ RESET BERHASIL ({$elapsed} detik).", 'ok', $logs);
    log_msg("", 'info', $logs);
    log_msg("LANGKAH SELANJUTNYA:", 'info', $logs);
    log_msg("1. HAPUS file public/go-live-reset.php dari server SEKARANG.", 'info', $logs);
    log_msg("2. Login via admin@kopidrip.com / password.", 'info', $logs);
    log_msg("3. Ganti password semua akun default (menu User).", 'info', $logs);
    log_msg("4. Isi saldo awal Kas (menu Keuangan → Kas) kalau perlu.", 'info', $logs);
    log_msg("5. Siap pakai. Selamat go-live!", 'info', $logs);

} catch (\Throwable $e) {
    log_msg("❌ GAGAL: " . $e->getMessage(), 'err', $logs);
    log_msg("File: " . $e->getFile() . ":" . $e->getLine(), 'err', $logs);
    log_msg("", 'err', $logs);
    log_msg("Jangan panik — database kemungkinan dalam state partial.", 'err', $logs);
    log_msg("1. Restore backup .sql via cPanel → phpMyAdmin → Import.", 'err', $logs);
    log_msg("2. Hubungi developer dengan screenshot error di atas.", 'err', $logs);
}

// Tulis log ke file
try {
    $logPath = __DIR__ . '/../storage/logs/go-live-reset.log';
    @file_put_contents($logPath, implode("\n", $logs) . "\n", FILE_APPEND);
} catch (\Throwable $e) {
    // ignore log write error
}
?>
</pre>
</body>
</html>
