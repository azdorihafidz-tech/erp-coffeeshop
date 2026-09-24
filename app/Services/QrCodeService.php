<?php

namespace App\Services;

use App\Models\Meja;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    /**
     * Generate QR code (SVG string) untuk 1 meja — encode URL menu publik
     * dengan qr_token sebagai auth guest (bukan login, cuma bukti "scan QR
     * asli", lihat FASE_E_QR_TABLE_ORDERING.md).
     */
    public function generate(int $mejaId): string
    {
        $meja = Meja::with('cabang')->findOrFail($mejaId);

        $url = $this->buildOrderUrl($meja);

        $svg = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->errorCorrection('Q')
            ->generate($url);

        // Buang XML declaration di baris pertama -- valid utk file .svg
        // berdiri sendiri, TAPI invalid kalau di-embed mentah ke tengah
        // dokumen HTML (dompdf berhenti/skip render elemen ini kalau
        // declaration itu masih ada). Sisakan cuma tag svg...svg saja.
        return preg_replace('/^<' . '\?xml.*?\?' . '>\s*/', '', $svg);
    }

    public function buildOrderUrl(Meja $meja): string
    {
        return rtrim(config('app.url'), '/')
            . '/order/' . $meja->cabang->kode_cabang
            . '/' . $meja->nomor_meja
            . '?token=' . $meja->qr_token;
    }
}
