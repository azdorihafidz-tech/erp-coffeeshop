<?php
/**
 * Deploy Sprint 4.39 Closing Periode + 4.40 Label Cup (2026-10-19)
 * ================================================================
 * Langkah:
 * 1. git pull di server
 * 2. Buka  /deploy-closing-label440.php?confirm=YES-CLOSING-LABEL&tahap=simulasi
 *    → hanya MELIHAT rencana, tidak ada yang diubah
 * 3. Backup database lewat cPanel (phpMyAdmin → Export)
 * 4. Buka alamat yang sama dengan &tahap=jalankan
 * 5. HAPUS file ini dari server (public/deploy-closing-label440.php)
 *
 * Aman: hanya menambah tabel/kolom baru + permission/panduan/tooltip.
 * Data transaksi, stok, kas, dan hak akses role yang sudah diatur TIDAK diubah.
 */

const CONFIRM = 'YES-CLOSING-LABEL';

header('Content-Type: text/html; charset=utf-8');

if (($_GET['confirm'] ?? '') !== CONFIRM) {
    http_response_code(403);
    exit('Akses ditolak. Tambahkan ?confirm=' . CONFIRM . '&tahap=simulasi di alamat.');
}

$tahap = $_GET['tahap'] ?? 'simulasi';
if (!in_array($tahap, ['simulasi', 'jalankan'], true)) {
    exit('Parameter tahap harus "simulasi" atau "jalankan".');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_time_limit(300);

$svc = $app->make(App\Services\UpdateDatabaseService::class);
$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$sebelum = $svc->rencana();
$logs = $tahap === 'jalankan' ? $svc->jalankan() : [];
$sesudah = $tahap === 'jalankan' ? $svc->jumlahData() : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Deploy 4.39–4.40 — <?= $e($tahap) ?></title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 760px; margin: 24px auto; padding: 0 16px; color: #1e293b; }
    h1 { font-size: 20px; } h2 { font-size: 16px; margin-top: 24px; }
    .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; background: #f8fafc; }
    .sim { background: #eff6ff; border-color: #93c5fd; } .run { background: #f0fdf4; border-color: #86efac; }
    table { border-collapse: collapse; width: 100%; } td, th { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; font-size: 14px; }
    .ok { color: #15803d; } .err { color: #b91c1c; font-weight: bold; } code { background: #e2e8f0; padding: 1px 4px; border-radius: 4px; }
    .btn { display: inline-block; padding: 10px 16px; background: #15803d; color: #fff; border-radius: 6px; text-decoration: none; }
</style>
</head>
<body>
<h1>Deploy Closing Periode + Label Cup</h1>

<?php if ($tahap === 'simulasi'): ?>
<div class="box sim"><strong>TAHAP SIMULASI</strong> — belum ada yang diubah. Periksa rencana di bawah, backup database, lalu jalankan.</div>
<?php else: ?>
<div class="box run"><strong>TAHAP JALANKAN</strong> — selesai dijalankan. Lihat hasil di bawah, lalu <strong>hapus file ini dari server</strong>.</div>
<?php endif; ?>

<h2>1. Migration (tabel/kolom baru)</h2>
<?php if (empty($sebelum['migration'])): ?>
<p class="ok">Tidak ada migration baru — database sudah sesuai kode.</p>
<?php else: ?>
<ul><?php foreach ($sebelum['migration'] as $m): ?><li><code><?= $e($m) ?></code></li><?php endforeach; ?></ul>
<?php endif; ?>

<h2>2. Konten yang diperbarui</h2>
<p><?= $e(implode(', ', $sebelum['seeders'])) ?> (permission, panduan Cara Pakai, tooltip)</p>

<h2>3. Hak akses yang <?= $tahap === 'simulasi' ? 'akan' : '' ?> ditambahkan</h2>
<?php if (empty($sebelum['hak_akses'])): ?>
<p class="ok">Semua hak akses fitur baru sudah ada.</p>
<?php else: ?>
<ul><?php foreach ($sebelum['hak_akses'] as $h): ?><li><?= $e($h) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<p style="font-size:13px">Hak akses yang sudah Anda ubah di menu Role &amp; Hak Akses tidak direset.</p>

<h2>4. Jumlah data (bukti data tidak hilang)</h2>
<table>
    <tr><th>Tabel</th><th>Sebelum</th><?php if ($sesudah): ?><th>Sesudah</th><?php endif; ?></tr>
    <?php foreach ($sebelum['jumlah_data'] as $t => $n): ?>
    <tr><td><?= $e($t) ?></td><td><?= $e($n ?? '-') ?></td>
        <?php if ($sesudah): ?><td class="<?= ($sesudah[$t] ?? null) === $n ? 'ok' : 'err' ?>"><?= $e($sesudah[$t] ?? '-') ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
</table>

<?php if ($logs): ?>
<h2>5. Hasil</h2>
<div class="box">
<?php foreach ($logs as $l): ?><div class="<?= $l['type'] === 'err' ? 'err' : ($l['type'] === 'ok' ? 'ok' : '') ?>">[<?= $e($l['ts']) ?>] <?= nl2br($e($l['msg'])) ?></div><?php endforeach; ?>
</div>
<p><strong>Langkah terakhir:</strong> hapus file <code>public/deploy-closing-label440.php</code> dari server.</p>
<?php else: ?>
<h2>Lanjutkan</h2>
<p>Sudah backup database? Klik untuk menjalankan:</p>
<p><a class="btn" href="?confirm=<?= $e(CONFIRM) ?>&amp;tahap=jalankan" onclick="return confirm('Sudah backup database? Lanjut jalankan?')">Jalankan Update</a></p>
<?php endif; ?>
</body>
</html>
