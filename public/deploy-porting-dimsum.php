<?php
/**
 * Deploy gabungan porting dimsum 4.50–4.53 (CLAUDE.md 4.57–4.61) — 2026-11-17
 * =========================================================================
 * Isi: Akun Tim IT + Aktivitas Tim IT, Paket & Langganan, perbaikan bug 1–21,
 *      Stok Opname lengkap, Kamus Menu, saklar QR, lokasi Roastery.
 *
 * Langkah (per instalasi: demo → go-live → WONGKO PI):
 * 1. Backup database (cPanel → phpMyAdmin → Export).
 * 2. Isi di .env server (folder utama, BUKAN public/), lalu simpan:
 *        TEKNISI_NAMA="Nama Orang IT"
 *        TEKNISI_EMAIL=email-tim-it@contoh.com
 *        TEKNISI_PASSWORD=kata-sandi-min-12-huruf-dan-angka   (BEDA per instalasi)
 * 3. cPanel → Git Version Control → Update from Remote → Deploy HEAD Commit.
 * 4. Buka  /deploy-porting-dimsum.php?confirm=YES-PORTING453&tahap=simulasi
 *    → hanya MELIHAT rencana & laporan, tidak ada yang diubah.
 * 5. Buka alamat yang sama dengan &tahap=jalankan
 *    (go-live Kopi Drip: tambahkan &es_batu_gram=ANGKA untuk komposisi ICE).
 * 6. Kabari developer → file ini dihapus dari GitHub → Update from Remote lagi.
 *
 * Aman: hanya migration baru, seeder idempotent, izin ADDITIVE (centangan bawaan
 * hanya untuk izin yang baru dibuat). Tidak memakai RolePermissionSeeder.
 * Data transaksi, stok, kas, dan hak akses yang sudah diatur Owner TIDAK diubah,
 * kecuali koreksi data ICE/TISU (hanya kalau barangnya ada) yang disetujui Owner.
 * Skrip menolak dijalankan dua kali (kunci storage/deploy-porting-dimsum.lock).
 */

const CONFIRM = 'YES-PORTING453';

header('Content-Type: text/html; charset=utf-8');

if (($_GET['confirm'] ?? '') !== CONFIRM) {
    http_response_code(403);
    exit('Akses ditolak. Tambahkan ?confirm=' . CONFIRM . '&tahap=simulasi di alamat.');
}
$tahap = $_GET['tahap'] ?? 'simulasi';
if (! in_array($tahap, ['simulasi', 'jalankan'], true)) {
    exit('Parameter tahap harus "simulasi" atau "jalankan".');
}

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_time_limit(600);

use App\Models\Cabang;
use App\Models\Item;
use App\Models\KategoriTransaksi;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
$kunci = storage_path('deploy-porting-dimsum.lock');
$sudahJalan = is_file($kunci);
if ($tahap === 'jalankan' && $sudahJalan) {
    exit('Deploy ini SUDAH pernah dijalankan (' . $e(trim((string) file_get_contents($kunci))) . '). Tidak dijalankan lagi. Minta developer menghapus file ini.');
}

$svc = $app->make(App\Services\UpdateDatabaseService::class);

// ---------- Tim IT dari .env (dibaca langsung dari file: env() kosong kalau config ter-cache) ----------
$envFile = base_path('.env');
$envIsi = is_file($envFile) ? Dotenv\Dotenv::parse((string) file_get_contents($envFile)) : [];
$tim = [
    'nama'  => trim((string) ($envIsi['TEKNISI_NAMA'] ?? getenv('TEKNISI_NAMA') ?: '')),
    'email' => strtolower(trim((string) ($envIsi['TEKNISI_EMAIL'] ?? getenv('TEKNISI_EMAIL') ?: ''))),
    'sandi' => (string) ($envIsi['TEKNISI_PASSWORD'] ?? getenv('TEKNISI_PASSWORD') ?: ''),
];
$timMasalah = [];
if ($tim['nama'] === '' || $tim['email'] === '' || $tim['sandi'] === '') {
    $timMasalah[] = 'TEKNISI_NAMA / TEKNISI_EMAIL / TEKNISI_PASSWORD belum lengkap di .env';
} else {
    if (! filter_var($tim['email'], FILTER_VALIDATE_EMAIL)) {
        $timMasalah[] = 'TEKNISI_EMAIL bukan alamat email yang benar';
    }
    if (mb_strlen($tim['sandi']) < 12 || ! preg_match('/[A-Za-z]/', $tim['sandi']) || ! preg_match('/\d/', $tim['sandi'])) {
        $timMasalah[] = 'TEKNISI_PASSWORD minimal 12 karakter dan berisi huruf + angka';
    }
}
$akunAda = $tim['email'] !== '' ? User::withTrashed()->where('email', $tim['email'])->first() : null;
$timStatus = $timMasalah ? implode('; ', $timMasalah)
    : ($akunAda ? ($akunAda->role?->value === 'teknisi' ? 'Akun Tim IT dengan email ini sudah ada — tidak diubah.' : 'Email ini sudah dipakai akun ' . $akunAda->role?->value . ' — akun Tim IT TIDAK dibuat (pakai email lain).')
        : 'Akan dibuat: ' . $tim['nama'] . ' (' . $tim['email'] . ')');

// ---------- Koreksi data ICE / TISU (bug 4, keputusan Owner) ----------
$esBatuGram = isset($_GET['es_batu_gram']) && is_numeric($_GET['es_batu_gram']) && (float) $_GET['es_batu_gram'] > 0 ? (float) $_GET['es_batu_gram'] : null;
$ice = Item::where('kode_item', 'PJ-KOP-008')->where('nama_item', 'ICE')->first();
$esBatu = Item::where('kode_item', 'BB-CAF-040')->where('nama_item', 'ES BATU')->first();
$tisu = Item::where('kode_item', 'BB-CAF-029')->where('nama_item', 'TISU')->first();
$iceSudahResep = $ice ? $ice->resep()->exists() : false;
$stokEsBatu = $esBatu ? (float) Stock::where('item_id', $esBatu->id)->sum('qty') : 0;

// ---------- Akun COA kategori (bug 16) — simulasi lewat transaksi yang dibatalkan ----------
$coaSebelum = KategoriTransaksi::withTrashed()->pluck('kode_akun_coa', 'kode')->all();
$coaRencana = [];
if ($tahap === 'simulasi') {
    DB::beginTransaction();
    try {
        (new Database\Seeders\KategoriCoaBackfillSeeder())->run();
        $coaRencana = KategoriTransaksi::withTrashed()->pluck('kode_akun_coa', 'kode')->all();
    } finally {
        DB::rollBack();
    }
}

// ---------- Info baca-saja ----------
$tanpaCabang = User::query()->where('is_active', true)->whereNotIn('role', ['owner', 'admin_pusat', 'teknisi'])->doesntHave('cabangs')->get(['name', 'email', 'role']);
$roastery = Cabang::where('tipe', 'roastery')->where('is_active', true)->get(['nama_cabang', 'kode_cabang']);
$qr = Cabang::where('is_active', true)->where('tipe', 'cabang')->get()->map(fn ($c) => [$c->nama_cabang, (bool) $c->qr_ordering_active, App\Models\Meja::where('cabang_id', $c->id)->count()]);
$tambahanHabis = Item::query()->where('is_active', true)
    ->where(fn ($q) => $q->where('tipe', 'produk_tambahan')->orWhere(fn ($w) => $w->where('tipe', 'tambahan_gratis')->where('track_stok', true)))
    ->doesntHave('resep')->get()->filter(fn ($i) => (float) Stock::where('item_id', $i->id)->sum('qty') <= 0)
    ->reject(fn ($i) => in_array($i->id, array_filter([$tisu?->id, ($ice && $esBatu) ? $ice->id : null]), true)) // dikoreksi di bagian 5
    ->pluck('nama_item');

$sebelum = $svc->rencana();
$logs = [];
$sesudah = null;
$coaSesudah = null;

if ($tahap === 'jalankan') {
    $logs = $svc->jalankan();
    $gagal = collect($logs)->contains(fn ($l) => $l['type'] === 'err');
    $log = function (string $msg, string $type = 'info') use (&$logs) {
        $logs[] = ['type' => $type, 'ts' => date('H:i:s'), 'msg' => $msg];
    };

    if (! $gagal) {
        // Akun Tim IT
        if ($timMasalah) {
            $log('Akun Tim IT tidak dibuat: ' . implode('; ', $timMasalah) . '. Isi .env lalu buat lewat menu Pengaturan → Tim IT (butuh akun Tim IT) atau jalankan deploy berikutnya.', 'err');
        } elseif ($akunAda) {
            $log('Akun Tim IT: ' . $timStatus, 'info');
        } else {
            $u = User::create(['name' => $tim['nama'], 'email' => $tim['email'], 'password' => Hash::make($tim['sandi']), 'role' => 'teknisi', 'is_active' => true]);
            activity('TimIT')->performedOn($u)->log("Akun Tim IT pertama dibuat oleh skrip deploy ({$u->email})");
            $log("Akun Tim IT dibuat: {$u->name} ({$u->email}).", 'ok');
        }

        // Koreksi ICE / TISU
        try {
            DB::transaction(function () use ($ice, $esBatu, $tisu, $iceSudahResep, $esBatuGram, $log) {
                if ($ice && $esBatu && ! $iceSudahResep) {
                    if ($esBatuGram) {
                        $resep = ResepBumbu::create(['nama' => $ice->nama_item, 'kode' => ResepBumbu::kodeUntukItem($ice), 'item_id' => $ice->id, 'is_active' => true]);
                        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $esBatu->id, 'qty_per_unit' => $esBatuGram,
                            'satuan' => 'gram', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 0]);
                        $log("ICE diberi komposisi ES BATU {$esBatuGram} gram per porsi.", 'ok');
                    } else {
                        $log('ICE belum diberi komposisi: parameter &es_batu_gram=ANGKA tidak diisi. Bisa diisi manual di Produk Jual → ICE → Komposisi.', 'err');
                    }
                }
                if ($tisu && $tisu->track_stok) {
                    $tisu->update(['track_stok' => false]);
                    $log('TISU: Lacak Stok dimatikan (tidak dihitung stoknya di POS).', 'ok');
                }
            });
        } catch (\Throwable $ex) {
            $log('Koreksi ICE/TISU gagal: ' . $ex->getMessage(), 'err');
        }

        Artisan::call('cache:clear');
        App\Support\Paket::lupakan();
        @file_put_contents($kunci, date('Y-m-d H:i:s'));
        $log('Kunci deploy dibuat — skrip ini tidak bisa dijalankan lagi.', 'ok');
    }
    $sesudah = $svc->jumlahData();
    $coaSesudah = KategoriTransaksi::withTrashed()->pluck('kode_akun_coa', 'kode')->all();
}

$coaBanding = $tahap === 'simulasi' ? $coaRencana : ($coaSesudah ?? []);
$coaBerubah = [];
foreach ($coaBanding as $kode => $akun) {
    if (($coaSebelum[$kode] ?? null) !== $akun) {
        $k = KategoriTransaksi::withTrashed()->where('kode', $kode)->first();
        $total = $k ? (float) DB::table('transaksi_keuangans')->whereNull('deleted_at')->where('kategori_id', $k->id)->sum('jumlah') : 0;
        $coaBerubah[] = [$kode, $k?->nama ?? '(kategori baru Stok Opname)', $coaSebelum[$kode] ?? '—', $akun ?? '—', $total];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Deploy porting dimsum — <?= $e($tahap) ?></title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 860px; margin: 24px auto; padding: 0 16px; color: #1e293b; }
    h1 { font-size: 20px; } h2 { font-size: 16px; margin-top: 26px; }
    .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; background: #f8fafc; }
    .sim { background: #eff6ff; border-color: #93c5fd; } .run { background: #f0fdf4; border-color: #86efac; } .warn { background: #fffbeb; border-color: #fcd34d; }
    table { border-collapse: collapse; width: 100%; } td, th { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; text-align: left; font-size: 14px; vertical-align: top; }
    .ok { color: #15803d; } .err { color: #b91c1c; font-weight: bold; } code { background: #e2e8f0; padding: 1px 4px; border-radius: 4px; }
    .btn { display: inline-block; padding: 10px 16px; background: #15803d; color: #fff; border-radius: 6px; text-decoration: none; }
    small { color: #475569; }
</style>
</head>
<body>
<h1>Deploy: Tim IT, Paket, Perbaikan Bug, Stok Opname, Kamus Menu</h1>

<?php if ($sudahJalan && $tahap === 'simulasi'): ?>
<div class="box warn"><strong>Deploy ini sudah pernah dijalankan</strong> (<?= $e(trim((string) file_get_contents($kunci))) ?>). Halaman ini hanya laporan.</div>
<?php elseif ($tahap === 'simulasi'): ?>
<div class="box sim"><strong>TAHAP SIMULASI</strong> — belum ada yang diubah. Periksa semua bagian di bawah, backup database, lalu jalankan.</div>
<?php else: ?>
<div class="box run"><strong>TAHAP JALANKAN</strong> — selesai. Lihat hasil di bawah, lalu kabari developer untuk menghapus file ini.</div>
<?php endif; ?>

<h2>1. Migration (tabel/kolom baru)</h2>
<?php if (empty($sebelum['migration'])): ?><p class="ok">Tidak ada migration baru.</p>
<?php else: ?><ul><?php foreach ($sebelum['migration'] as $m): ?><li><code><?= $e($m) ?></code></li><?php endforeach; ?></ul><?php endif; ?>

<h2>2. Hak akses baru yang <?= $tahap === 'simulasi' ? 'akan' : '' ?> dicentangkan ke role bawaan</h2>
<?php if (empty($sebelum['hak_akses'])): ?><p class="ok">Tidak ada (semua izin sudah ada — centangan Anda tidak disentuh).</p>
<?php else: ?><p><?= count($sebelum['hak_akses']) ?> centangan:</p><ul style="columns:2;font-size:13px"><?php foreach ($sebelum['hak_akses'] as $h): ?><li><?= $e($h) ?></li><?php endforeach; ?></ul><?php endif; ?>
<small>Hanya untuk izin yang baru dibuat. Centangan yang sudah Anda ubah di Role &amp; Hak Akses tidak direset.</small>

<h2>3. Akun Tim IT (dari .env)</h2>
<p class="<?= $timMasalah ? 'err' : 'ok' ?>"><?= $e($timStatus) ?></p>
<small>Kata sandi tidak ditampilkan. Akun Tim IT tidak terlihat oleh Owner, punya akses penuh, dan setiap tindakannya tercatat di Keamanan → Aktivitas Tim IT.</small>

<h2>4. Akun COA kategori transaksi — efek ke Laba Rugi Formal</h2>
<?php if (! $coaBerubah): ?><p class="ok">Tidak ada perubahan pemetaan akun.</p>
<?php else: ?>
<p>Kategori di bawah <?= $tahap === 'simulasi' ? 'akan' : 'sudah' ?> ditautkan ke akun COA, sehingga transaksinya mulai masuk <strong>Laba Rugi Formal</strong> (angka laba di laporan itu bisa turun ke angka sebenarnya). Arsip Closing Periode yang sudah dikunci tidak berubah.</p>
<table><tr><th>Kode</th><th>Kategori</th><th>Akun lama</th><th>Akun baru</th><th>Total transaksi selama ini</th></tr>
<?php foreach ($coaBerubah as [$kode, $nama, $lama, $baru, $total]): ?>
<tr><td><code><?= $e($kode) ?></code></td><td><?= $e($nama) ?></td><td><?= $e($lama) ?></td><td><?= $e($baru) ?></td><td><?= $e($rp($total)) ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<small>Transfer antar kas, setoran kasir, transfer dana, saldo awal, dan retur pembelian sengaja tanpa akun (tidak dihitung sebagai laba/rugi). Akun HPP (5-xxxx, mis. Pembelian Bahan Baku) tidak dihitung dari kas — HPP di Laba Rugi Formal tetap diambil dari modal barang yang terjual, jadi tidak dobel.</small>

<h2>5. Koreksi data ICE / TISU (keputusan Owner, bug 4)</h2>
<?php if (! $ice && ! $tisu): ?>
<p class="ok">Barang ICE (PJ-KOP-008) / TISU (BB-CAF-029) tidak ada di instalasi ini — tidak ada koreksi.</p>
<?php else: ?>
<ul>
<?php if ($ice): ?><li><strong>ICE</strong>: <?= $iceSudahResep ? 'sudah punya komposisi — tidak diubah.' : ($esBatu ? ($esBatuGram ? 'akan diberi komposisi <strong>ES BATU ' . $e($esBatuGram) . ' gram</strong> per porsi.' : '<span class="err">butuh takaran: tambahkan <code>&amp;es_batu_gram=ANGKA</code> di alamat.</span>') : 'barang ES BATU (BB-CAF-040) tidak ada.') ?>
    <?php if ($esBatu): ?><br><small>Stok ES BATU sekarang: <?= $e($stokEsBatu) ?> gram. <?= $stokEsBatu <= 0 ? 'ICE akan tertulis "Habis" di POS sampai stok es batu diisi (Penyesuaian Stok / PO).' : '' ?></small><?php endif; ?></li><?php endif; ?>
<?php if ($tisu): ?><li><strong>TISU</strong>: <?= $tisu->track_stok ? 'Lacak Stok akan dimatikan (tidak dihitung stoknya di POS).' : 'Lacak Stok sudah mati.' ?></li><?php endif; ?>
</ul>
<?php endif; ?>
<?php if ($tambahanHabis->isNotEmpty()): ?>
<p class="err">Item Tambahan yang akan tertulis "Habis" di POS (tanpa komposisi & stok 0): <?= $e($tambahanHabis->implode(', ')) ?></p>
<small>Sejak perbaikan bug 4, Produk Tambahan dan Tambahan Gratis ber-Lacak Stok memotong stoknya. Beri komposisi, isi stok, atau matikan Lacak Stok (Tambahan Gratis).</small>
<?php endif; ?>

<h2>6. Perlu diketahui (tidak diubah oleh skrip)</h2>
<ul>
    <li><strong>Lokasi Roastery</strong>: <?= $roastery->isEmpty() ? 'belum ada cabang bertipe Roastery — menu Roastery menampilkan peringatan sampai dibuat.' : $e($roastery->map(fn ($c) => $c->nama_cabang . ' (' . $c->kode_cabang . ')')->implode(', ')) ?></li>
    <li><strong>Pesan lewat QR</strong> (Cabang → Edit → Config POS):
        <?php foreach ($qr as [$nama, $aktif, $meja]): ?><?= $e($nama) ?> = <?= $aktif ? 'nyala' : 'mati' ?> (<?= $e($meja) ?> meja); <?php endforeach; ?></li>
    <li><strong>User tanpa cabang</strong> (tidak bisa memakai menu yang butuh cabang): <?= $tanpaCabang->isEmpty() ? 'tidak ada' : $e($tanpaCabang->map(fn ($u) => $u->name . ' – ' . $u->role?->value)->implode('; ')) ?></li>
    <li><strong>Paket</strong>: semua modul aktif tanpa batas waktu (tidak ada yang berubah untuk pengguna).</li>
    <li><strong>Bayar gaji</strong> sekarang mengurangi kas; <strong>edit order yang sudah dibayar</strong> tidak bisa mengubah diskon/metode bayar; hapus barang/pelanggan/cabang yang masih dipakai ditolak.</li>
</ul>

<h2>7. Jumlah data (bukti data tidak hilang)</h2>
<table>
    <tr><th>Tabel</th><th>Sebelum</th><?php if ($sesudah): ?><th>Sesudah</th><?php endif; ?></tr>
    <?php foreach ($sebelum['jumlah_data'] as $t => $n): ?>
    <tr><td><?= $e($t) ?></td><td><?= $e($n ?? '-') ?></td>
        <?php if ($sesudah): ?><td class="<?= ($sesudah[$t] ?? null) === $n || ($t === 'users' && ($sesudah[$t] ?? 0) - $n <= 1) ? 'ok' : 'err' ?>"><?= $e($sesudah[$t] ?? '-') ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
</table>
<small>users boleh bertambah 1 (akun Tim IT).</small>

<?php if ($logs): ?>
<h2>8. Hasil</h2>
<div class="box">
<?php foreach ($logs as $l): ?><div class="<?= $l['type'] === 'err' ? 'err' : ($l['type'] === 'ok' ? 'ok' : '') ?>">[<?= $e($l['ts']) ?>] <?= nl2br($e($l['msg'])) ?></div><?php endforeach; ?>
</div>
<p><strong>Langkah terakhir:</strong> login dengan akun Tim IT sekali untuk mencoba, lalu kabari developer supaya file ini dihapus dari GitHub.</p>
<?php elseif (! $sudahJalan): ?>
<h2>Lanjutkan</h2>
<p>Sudah backup database? Klik untuk menjalankan:</p>
<p><a class="btn" href="?confirm=<?= $e(CONFIRM) ?>&amp;tahap=jalankan<?= $esBatuGram ? '&amp;es_batu_gram=' . $e($esBatuGram) : '' ?>" onclick="return confirm('Sudah backup database? Lanjut jalankan?')">Jalankan Update</a></p>
<?php endif; ?>
</body>
</html>
