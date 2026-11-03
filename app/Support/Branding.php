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
    public const CACHE_KEY = 'branding:v1';
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
