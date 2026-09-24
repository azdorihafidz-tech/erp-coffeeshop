<?php

namespace App\Services;

use App\Models\Meja;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

class QrCodeService
{
    private const MODUL_PX  = 10; // piksel per modul QR
    private const QUIET_MOD = 4;  // quiet zone standar QR (4 modul)

    /**
     * QR untuk 1 meja sebagai data URI PNG — encode URL menu publik dengan
     * qr_token (bukan login, cuma bukti "scan QR asli", lihat
     * FASE_E_QR_TABLE_ORDERING.md).
     *
     * Kenapa PNG via GD, bukan SVG: dompdf TIDAK merender elemen <svg> inline
     * (hanya <img>), dan format PNG di simple-qrcode butuh ekstensi imagick
     * yang tidak ada di XAMPP maupun banyak shared hosting. Matriks QR diambil
     * dari bacon/bacon-qr-code (dependensi simple-qrcode), digambar dengan GD.
     */
    public function generate(int $mejaId): string
    {
        $meja = Meja::with('cabang')->findOrFail($mejaId);

        return $this->pngDataUri($this->buildOrderUrl($meja));
    }

    public function pngDataUri(string $content): string
    {
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::Q(), 'UTF-8')->getMatrix();
        $n      = $matrix->getWidth();
        $size   = ($n + 2 * self::QUIET_MOD) * self::MODUL_PX;

        $img   = imagecreatetruecolor($size, $size);
        $putih = imagecolorallocate($img, 255, 255, 255);
        $hitam = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $putih);

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + self::QUIET_MOD) * self::MODUL_PX;
                    $py = ($y + self::QUIET_MOD) * self::MODUL_PX;
                    imagefilledrectangle($img, $px, $py, $px + self::MODUL_PX - 1, $py + self::MODUL_PX - 1, $hitam);
                }
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    public function buildOrderUrl(Meja $meja): string
    {
        return rtrim(config('app.url'), '/')
            . '/order/' . $meja->cabang->kode_cabang
            . '/' . $meja->nomor_meja
            . '?token=' . $meja->qr_token;
    }
}
