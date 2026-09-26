<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Kas;
use Illuminate\Database\Seeder;

/**
 * Kas testing (2026-09-23, blocker E3 closeBill()) — 3 Kas per outlet
 * (OUT001-OUT005): Tunai, Transfer, QRIS — sesuai 3 metode pembayaran aktif
 * (App\Enums\TipePembayaran). Gudang Pusat (GP001) TIDAK diberi Kas (bukan
 * tempat transaksi POS). Saldo awal 500rb (Tunai) / 0 (Transfer & QRIS,
 * non-tunai tidak perlu modal kembalian awal) per outlet.
 *
 * Catatan skema: tidak ada kolom kode_kas/currency/keterangan di tabel
 * `kas` (lihat app/Models/Kas.php) — disesuaikan ke kolom yang genuinely
 * ada: nama_kas, tipe_kas, default_untuk, saldo_awal/saldo_sekarang.
 *
 * Extended 2026-09-23 (E4.4 blocker split-payment) — awalnya cuma Tunai,
 * split payment (Tunai+Transfer) gagal krn Kas Transfer/QRIS belum ada.
 */
class KasSeeder extends Seeder
{
    private const METODE = [
        'tunai'    => ['label' => 'Kas Tunai',    'tipe_kas' => 'tunai', 'saldo_awal' => 500000],
        'transfer' => ['label' => 'Kas Transfer', 'tipe_kas' => 'bank',  'saldo_awal' => 0],
        'qris'     => ['label' => 'Kas QRIS',     'tipe_kas' => 'bank',  'saldo_awal' => 0],
    ];

    public function run(): void
    {
        $outlets = Cabang::whereIn('kode_cabang', ['OUT001', 'OUT002', 'OUT003', 'OUT004', 'OUT005'])->get();

        if ($outlets->count() !== 5) {
            $this->command?->warn('KasSeeder: 5 outlet tidak lengkap, di-skip.');
            return;
        }

        // Roastery V2 Minggu 6-7 (2026-09-30) — RST001 butuh Kas sendiri utk POS Roastery.
        // Instruksi awal cuma minta Tunai+Transfer, tapi disamakan pola outlet (3 metode
        // penuh, termasuk QRIS) supaya tidak mengulang blocker E3 (Kas Transfer/QRIS
        // hilang bikin split-payment/QRIS gagal — lihat CLAUDE.md 4.12 & 4.14).
        $rst = Cabang::where('kode_cabang', 'RST001')->first();
        if ($rst) {
            $outlets->push($rst);
        }

        foreach ($outlets as $cabang) {
            foreach (self::METODE as $defaultUntuk => $cfg) {
                $existing = Kas::where('cabang_id', $cabang->id)->where('default_untuk', $defaultUntuk)->first();

                if ($existing) {
                    // Idempotent TAPI JANGAN reset saldo_sekarang (live balance,
                    // bisa sudah berubah dari transaksi nyata) -- cuma sync
                    // metadata display (nama/tipe/aktif).
                    $existing->update([
                        'nama_kas'  => "{$cfg['label']} {$cabang->nama_cabang}",
                        'tipe_kas'  => $cfg['tipe_kas'],
                        'is_active' => true,
                    ]);
                    continue;
                }

                Kas::create([
                    'cabang_id'      => $cabang->id,
                    'nama_kas'       => "{$cfg['label']} {$cabang->nama_cabang}",
                    'tipe_kas'       => $cfg['tipe_kas'],
                    'default_untuk'  => $defaultUntuk,
                    'saldo_awal'     => $cfg['saldo_awal'],
                    'saldo_sekarang' => $cfg['saldo_awal'],
                    'saldo_minimum'  => 0,
                    'is_active'      => true,
                ]);
            }
        }

        $this->command?->info('KasSeeder selesai: ' . ($outlets->count() * 3) . ' Kas (3 metode x ' . $outlets->count() . ' lokasi) di-seed/verified.');
    }
}
