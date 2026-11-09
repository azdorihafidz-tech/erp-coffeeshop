<?php

namespace App\Support;

use App\Models\PengaturanUmum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Sprint Identitas Usaha (2026-11-03) — nama, logo, tagline & kontak usaha
 * dari Pengaturan Umum. Satu-satunya sumber untuk login, sidebar, struk,
 * PDF, PWA, label cup, menu QR dan aplikasi kasir: kode yang sama dipakai
 * beberapa usaha (database berbeda), jadi JANGAN menulis nama/logo usaha
 * langsung di view — pakai `$brand` (dibagikan ke semua view).
 *
 * Logo upload disimpan di public/uploads/branding (diabaikan git) supaya
 * update program lewat git pull tidak menimpa logo masing-masing usaha.
 */
class Branding
{
    // v2 (4.54): data cache ikut membawa warna tampilan
    public const CACHE_KEY = 'branding:v2';

    /** Warna bawaan program (dipakai selama Pengaturan Umum belum mengatur warna). */
    public const WARNA_BAWAAN = ['utama' => '#2D6A4F', 'sidebar' => '#1A1A1A', 'latar' => '#F5F0E6'];

    /** Tema siap pakai di Pengaturan Umum (semua lolos aturan keterbacaan). */
    public const TEMA = [
        'hijau'   => ['nama' => 'Hijau Kopi',   'utama' => '#2D6A4F', 'sidebar' => '#1A1A1A', 'latar' => '#F5F0E6'],
        'cokelat' => ['nama' => 'Cokelat Kopi', 'utama' => '#7B4B2A', 'sidebar' => '#2B1B12', 'latar' => '#F6EFE7'],
        'biru'    => ['nama' => 'Biru Laut',    'utama' => '#1D4E89', 'sidebar' => '#0F1B2D', 'latar' => '#EEF3F9'],
        'merah'   => ['nama' => 'Merah Bata',   'utama' => '#A4372A', 'sidebar' => '#2A1414', 'latar' => '#FBF0EE'],
        'oranye'  => ['nama' => 'Oranye',       'utama' => '#C2410C', 'sidebar' => '#1F1A17', 'latar' => '#FFF7ED'],
        'ungu'    => ['nama' => 'Ungu',         'utama' => '#6B3FA0', 'sidebar' => '#1E1530', 'latar' => '#F5F1FA'],
    ];

    /** Batas keterbacaan (rasio kontras WCAG). Tulisan di atas warna utama & sidebar selalu putih. */
    public const KONTRAS_UTAMA_PUTIH = 3.0;   // tombol & judul tebal
    public const KONTRAS_UTAMA_LATAR = 3.0;   // ikon/judul warna utama di atas latar
    public const KONTRAS_SIDEBAR_PUTIH = 4.5; // teks menu sidebar
    public const KONTRAS_LATAR_TEKS = 10.0;   // latar harus terang (teks hitam #1A1A1A)
    public const FOLDER = 'uploads/branding';
    public const UKURAN_IKON = [72, 96, 128, 144, 152, 192, 384, 512];

    /** Logo bawaan repo — dipakai sebelum usaha meng-upload logo sendiri. */
    private const LOGO_BAWAAN = 'images/logo.png';

    private ?array $data = null;

    public function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        try {
            $this->data = Cache::rememberForever(self::CACHE_KEY, function () {
                $s = PengaturanUmum::query()->first();

                return $s ? [
                    'nama'         => $s->nama_perusahaan,
                    'nama_singkat' => $s->nama_singkat,
                    'tagline'      => $s->tagline,
                    'alamat'       => $s->alamat_perusahaan,
                    'telepon'      => $s->telepon,
                    'logo_path'    => $s->logo_path,
                    // Kolom warna belum ada sebelum migration 4.54 dijalankan → null = bawaan
                    'warna_utama'   => $s->getAttribute('warna_utama'),
                    'warna_sidebar' => $s->getAttribute('warna_sidebar'),
                    'warna_latar'   => $s->getAttribute('warna_latar'),
                    'versi'        => $s->updated_at?->timestamp,
                ] : [];
            });
        } catch (\Throwable) {
            // Database belum siap (instalasi baru, halaman error 500) — pakai bawaan
            $this->data = [];
        }

        return $this->data;
    }

    /** Hapus cache setelah Pengaturan Umum disimpan. */
    public function lupakan(): void
    {
        $this->data = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }

    public function nama(): string
    {
        return trim((string) ($this->data()['nama'] ?? '')) ?: (config('app.name') ?: 'ERP');
    }

    /** Untuk tempat sempit: sidebar, judul tab, struk, ikon aplikasi. */
    public function namaSingkat(): string
    {
        return trim((string) ($this->data()['nama_singkat'] ?? '')) ?: $this->nama();
    }

    public function tagline(): ?string
    {
        return trim((string) ($this->data()['tagline'] ?? '')) ?: null;
    }

    public function alamat(): ?string
    {
        return trim((string) ($this->data()['alamat'] ?? '')) ?: null;
    }

    public function telepon(): ?string
    {
        return trim((string) ($this->data()['telepon'] ?? '')) ?: null;
    }

    /** Footer struk kalau cabang belum mengisi Footer Struk sendiri. */
    public function footerStruk(): string
    {
        return "Terima kasih atas kunjungan Anda!\n" . $this->nama() . ($this->tagline() ? ' - ' . $this->tagline() : '');
    }

    /** Ganti penanda {nama_usaha} / {nama_singkat} di teks panduan & tooltip. */
    public function isiPenanda(?string $teks): string
    {
        return str_replace(['{nama_usaha}', '{nama_singkat}'], [$this->nama(), $this->namaSingkat()], (string) $teks);
    }

    // ----------------------------------------------------------- warna (4.54)

    public function warnaUtama(): string
    {
        return $this->warna('utama');
    }

    public function warnaSidebar(): string
    {
        return $this->warna('sidebar');
    }

    public function warnaLatar(): string
    {
        return $this->warna('latar');
    }

    /** true kalau Owner sudah memilih warna sendiri (minimal satu). */
    public function warnaDiatur(): bool
    {
        foreach (['utama', 'sidebar', 'latar'] as $k) {
            if (self::hexValid($this->data()['warna_' . $k] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** Warna utama digelapkan (hover tombol, dll.). */
    public function warnaUtamaGelap(float $porsi = 0.15): string
    {
        return self::campur($this->warnaUtama(), '#000000', $porsi);
    }

    /** "rgba(r,g,b,a)" dari warna hex — untuk bayangan/gradasi transparan. */
    public static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    /** "r,g,b" — untuk variabel Bootstrap `--bs-*-rgb`. */
    public static function rgbTeks(string $hex): string
    {
        return implode(',', self::rgb($hex));
    }

    /** Campur dua warna; $porsi = bagian warna kedua (0–1). */
    public static function campur(string $a, string $b, float $porsi): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);
        $hasil = '';
        for ($i = 0; $i < 3; $i++) {
            $hasil .= sprintf('%02X', (int) round($x[$i] + ($y[$i] - $x[$i]) * $porsi));
        }

        return '#' . $hasil;
    }

    public static function hexValid(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) === 1;
    }

    /** Rasio kontras WCAG 2 (1–21). */
    public static function kontras(string $a, string $b): float
    {
        $la = self::luminansi($a);
        $lb = self::luminansi($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Cek keterbacaan 3 warna. Return daftar pesan (kosong = aman).
     * Kunci = nama field, supaya bisa dipakai sebagai error validasi.
     */
    public static function cekKeterbacaan(string $utama, string $sidebar, string $latar): array
    {
        $pesan = [];
        if (self::kontras($utama, '#FFFFFF') < self::KONTRAS_UTAMA_PUTIH) {
            $pesan['warna_utama'] = 'Warna utama terlalu terang — tulisan putih di tombol tidak terbaca. Pilih warna yang lebih gelap.';
        } elseif (self::kontras($utama, $latar) < self::KONTRAS_UTAMA_LATAR) {
            $pesan['warna_utama'] = 'Warna utama terlalu mirip dengan warna latar — judul dan ikon tidak terlihat jelas.';
        }
        if (self::kontras($sidebar, '#FFFFFF') < self::KONTRAS_SIDEBAR_PUTIH) {
            $pesan['warna_sidebar'] = 'Warna sidebar terlalu terang — tulisan menu (putih) tidak terbaca. Pilih warna yang lebih gelap.';
        }
        if (self::kontras($latar, '#1A1A1A') < self::KONTRAS_LATAR_TEKS) {
            $pesan['warna_latar'] = 'Warna latar terlalu gelap — tulisan hitam di halaman tidak terbaca. Pilih warna yang lebih terang.';
        }

        return $pesan;
    }

    /** Warna tema ke manifest PWA (bilah atas HP saat aplikasi dipasang). */
    private function terapkanWarnaKeConfigPwa(): void
    {
        if (!$this->warnaDiatur()) {
            return; // bawaan: biarkan config/laravelpwa.php
        }
        config([
            'laravelpwa.manifest.theme_color'      => $this->warnaUtama(),
            'laravelpwa.manifest.background_color' => $this->warnaSidebar(),
        ]);
    }

    private function warna(string $jenis): string
    {
        $v = $this->data()['warna_' . $jenis] ?? null;

        return self::hexValid($v) ? strtoupper($v) : self::WARNA_BAWAAN[$jenis];
    }

    /** @return array{int,int,int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminansi(string $hex): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    // ------------------------------------------------------------------ logo

    public function adaLogoUpload(): bool
    {
        return $this->lokasiLogo()['jenis'] !== 'bawaan';
    }

    /** URL logo untuk <img> di halaman web. */
    public function logoUrl(): string
    {
        $l = $this->lokasiLogo();

        return match ($l['jenis']) {
            'upload' => asset($l['path']) . $this->versiQuery(),
            'lama'   => url('/img/' . $l['path']) . $this->versiQuery(),
            default  => asset(self::LOGO_BAWAAN),
        };
    }

    /** Path file logo di disk — untuk PDF (dompdf). null kalau tidak ada. */
    public function logoFile(): ?string
    {
        $l = $this->lokasiLogo();

        return $l['file'] && is_file($l['file']) ? $l['file'] : null;
    }

    /** URL ikon persegi (favicon, PWA, aplikasi kasir). */
    public function ikonUrl(int $ukuran = 192): string
    {
        $rel = self::FOLDER . "/icon-{$ukuran}.png";
        if ($this->adaLogoUpload() && is_file(public_path($rel))) {
            return asset($rel) . $this->versiQuery();
        }

        return is_file(public_path("images/icons/icon-{$ukuran}x{$ukuran}.png"))
            ? asset("images/icons/icon-{$ukuran}x{$ukuran}.png")
            : asset('images/logo-icon.png');
    }

    /** Nama, nama singkat & ikon ke manifest PWA (laravelpwa membaca config). */
    public function terapkanKeConfigPwa(): void
    {
        $ikon = [];
        foreach (self::UKURAN_IKON as $u) {
            $rel = self::FOLDER . "/icon-{$u}.png";
            $path = $this->adaLogoUpload() && is_file(public_path($rel))
                ? '/' . $rel
                : "/images/icons/icon-{$u}x{$u}.png";
            $ikon["{$u}x{$u}"] = ['path' => $path, 'purpose' => in_array($u, [192, 512], true) ? 'any maskable' : 'any'];
        }

        config([
            'laravelpwa.name'                => $this->nama(),
            'laravelpwa.manifest.name'       => $this->nama(),
            'laravelpwa.manifest.short_name' => $this->namaSingkat(),
            'laravelpwa.manifest.icons'      => $ikon,
        ]);
        $this->terapkanWarnaKeConfigPwa();
    }

    /** Data untuk aplikasi kasir Android (endpoint /app-info). */
    public function untukAplikasi(): array
    {
        return [
            'nama'         => $this->nama(),
            'nama_singkat' => $this->namaSingkat(),
            'tagline'      => $this->tagline(),
            'logo_url'     => $this->logoUrl(),
            'ikon_url'     => $this->ikonUrl(192),
        ];
    }

    /**
     * Simpan logo upload sebagai PNG (diperkecil maks 1024 px) + buat ikon
     * persegi semua ukuran. Return path relatif public/ untuk logo_path.
     */
    public function simpanLogo(UploadedFile $file): string
    {
        $folder = public_path(self::FOLDER);
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $nama = 'logo-' . now()->format('YmdHis') . '.png';
        $tujuan = $folder . DIRECTORY_SEPARATOR . $nama;

        $batasLama = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            if ($src === false) {
                throw new \RuntimeException('File logo tidak bisa dibaca sebagai gambar.');
            }
            $logo = $this->skala($src, 1024);
            imagepng($logo, $tujuan, 6);

            foreach (self::UKURAN_IKON as $u) {
                $ikon = $this->kotak($logo, $u);
                imagepng($ikon, $folder . DIRECTORY_SEPARATOR . "icon-{$u}.png", 6);
                imagedestroy($ikon);
            }
            imagedestroy($logo);
        } finally {
            ini_set('memory_limit', $batasLama);
        }

        $this->hapusLogoLama($nama);

        return self::FOLDER . '/' . $nama;
    }

    /** Hapus semua file logo & ikon upload (kembali ke logo bawaan). */
    public function hapusFileLogo(): void
    {
        $this->hapusLogoLama(null);
        foreach (self::UKURAN_IKON as $u) {
            @unlink(public_path(self::FOLDER . "/icon-{$u}.png"));
        }
    }

    // ----------------------------------------------------------- internal

    /** @return array{jenis: string, path: ?string, file: ?string} */
    private function lokasiLogo(): array
    {
        $p = ltrim((string) ($this->data()['logo_path'] ?? ''), '/');

        if ($p !== '' && str_starts_with($p, self::FOLDER . '/') && is_file(public_path($p))) {
            return ['jenis' => 'upload', 'path' => $p, 'file' => public_path($p)];
        }
        // Logo yang di-upload sebelum sprint ini (disk public, dilayani /img/...)
        if ($p !== '' && !str_contains($p, '..') && Storage::disk('public')->exists($p)) {
            return ['jenis' => 'lama', 'path' => $p, 'file' => Storage::disk('public')->path($p)];
        }

        return ['jenis' => 'bawaan', 'path' => self::LOGO_BAWAAN, 'file' => public_path(self::LOGO_BAWAAN)];
    }

    private function versiQuery(): string
    {
        $v = $this->data()['versi'] ?? null;

        return $v ? '?v=' . $v : '';
    }

    private function hapusLogoLama(?string $kecuali): void
    {
        foreach (glob(public_path(self::FOLDER . '/logo-*.png')) ?: [] as $f) {
            if ($kecuali === null || basename($f) !== $kecuali) {
                @unlink($f);
            }
        }
    }

    /** Perkecil (tidak memperbesar) dengan menjaga transparansi. */
    private function skala(\GdImage $src, int $maks): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $r = min(1, $maks / max($w, $h));
        $nw = max(1, (int) round($w * $r));
        $nh = max(1, (int) round($h * $r));

        $dst = $this->kanvas($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        return $dst;
    }

    /** Logo di tengah kanvas persegi transparan (jarak tepi 8%). */
    private function kotak(\GdImage $logo, int $u): \GdImage
    {
        $w = imagesx($logo);
        $h = imagesy($logo);
        $isi = $u * 0.84;
        $r = $isi / max($w, $h);
        $nw = max(1, (int) round($w * $r));
        $nh = max(1, (int) round($h * $r));

        $dst = $this->kanvas($u, $u);
        imagecopyresampled($dst, $logo, (int) (($u - $nw) / 2), (int) (($u - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function kanvas(int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        // Blending tetap mati: piksel logo (termasuk transparansinya) disalin apa adanya

        return $img;
    }
}
