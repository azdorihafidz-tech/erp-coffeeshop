<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Models\ClosingPeriode;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sprint Closing Periode (2026-10-17) — export Excel snapshot closing.
 * Angka diambil dari snapshot tersimpan (bukan data hidup), 5 sheet.
 */
class ClosingPeriodeExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private ClosingPeriode $closing, private ?string $namaUser = null)
    {
    }

    public function sheets(): array
    {
        $d = $this->closing->data ?? [];
        $c = $this->closing;
        $lr = $d['laba_rugi'] ?? [];
        $pj = $d['penjualan'] ?? [];
        $kas = $d['kas'] ?? [];
        $keu = $d['keuangan'] ?? [];
        $mut = $keu['mutasi'] ?? [];
        $stok = $d['stok'] ?? [];

        $ringkasan = [
            ['Kode Closing', $c->kode . ' (versi ' . $c->versi . ')'],
            ['Jenis', $c->labelJenis()],
            ['Periode', $c->labelPeriode()],
            ['Cakupan', $c->namaCakupan()],
            ['Status', ucfirst($c->status)],
            ['Dihitung pada', $d['meta']['dihitung_pada'] ?? '-'],
            ['', ''],
            ['Jumlah Transaksi', (string) (int) ($pj['jumlah_transaksi'] ?? 0)],
            ['Omzet Kotor', (float) ($pj['omzet_kotor'] ?? 0)],
            ['Diskon', (float) ($pj['diskon'] ?? 0)],
            ['Omzet Bersih', (float) ($lr['omzet_bersih'] ?? 0)],
            ['Eliminasi Penjualan Internal', -(float) ($lr['eliminasi_omzet'] ?? 0)],
            ['Omzet', (float) ($lr['omzet'] ?? 0)],
            ['HPP', -(float) ($lr['hpp'] ?? 0)],
            ['Laba Kotor', (float) ($lr['laba_kotor'] ?? 0)],
            ['Pemasukan Lain', (float) ($lr['pemasukan_lain'] ?? 0)],
            ['Beban Operasional', -(float) ($lr['beban'] ?? 0)],
            ['Laba Bersih', (float) ($lr['laba_bersih'] ?? 0)],
            ['', ''],
            ['Kas Tunai Akhir', (float) ($kas['total_tunai'] ?? 0)],
            ['Kas Bank/Non-Tunai Akhir', (float) ($kas['total_non_tunai'] ?? 0)],
            ['Total Kas Akhir', (float) ($kas['total_akhir'] ?? 0)],
            ['Dalam Perjalanan (setoran belum diterima)', (float) ($keu['dalam_perjalanan'] ?? 0)],
            ['Nilai Stok Akhir (estimasi)', (float) ($stok['total_nilai'] ?? 0)],
        ];

        $kasRows = [['Cabang', 'Kas', 'Jenis', 'Saldo Awal', 'Masuk', 'Keluar', 'Saldo Akhir']];
        foreach ($kas['detail'] ?? [] as $k) {
            $kasRows[] = [$k['cabang'], $k['nama_kas'], $k['jenis'] === 'tunai' ? 'Tunai' : 'Bank/Non-Tunai',
                $k['saldo_awal'], $k['masuk'], $k['keluar'], $k['saldo_akhir']];
        }

        $keuRows = [['Kelompok', 'Kategori', 'Jumlah']];
        foreach ($keu['pemasukan_lain'] ?? [] as $r) {
            $keuRows[] = ['Pemasukan Lain', $r['label'], $r['total']];
        }
        foreach ($keu['beban'] ?? [] as $r) {
            $keuRows[] = ['Beban Operasional', $r['label'], $r['total']];
        }
        foreach ($keu['persediaan_aset'] ?? [] as $r) {
            $keuRows[] = ['Pembelian Persediaan/Aset (bukan beban)', $r['label'], $r['total']];
        }
        $keuRows[] = ['Mutasi', 'Setoran keluar (diterima)', (float) ($mut['setoran_keluar_diterima'] ?? 0)];
        $keuRows[] = ['Mutasi', 'Setoran keluar (dalam perjalanan)', (float) ($mut['setoran_keluar_menunggu'] ?? 0)];
        $keuRows[] = ['Mutasi', 'Setoran masuk (diterima)', (float) ($mut['setoran_masuk_diterima'] ?? 0)];
        $keuRows[] = ['Mutasi', 'Transfer antar kas', (float) ($mut['transfer_antar_kas'] ?? 0)];
        $keuRows[] = ['Mutasi', 'Setoran kasir keluar', (float) ($mut['setoran_kasir_keluar'] ?? 0)];
        $keuRows[] = ['Mutasi', 'Setoran kasir masuk', (float) ($mut['setoran_kasir_masuk'] ?? 0)];

        $produkRows = [['Produk', 'Qty', 'Satuan', 'Omzet', 'HPP', 'Laba']];
        foreach ($pj['top_produk'] ?? [] as $p) {
            $produkRows[] = [$p['nama'], $p['qty'], $p['satuan'], $p['omzet'], $p['hpp'], $p['omzet'] - $p['hpp']];
        }

        $stokRows = [['Lokasi', 'Kode', 'Item', 'Satuan', 'Qty', 'Harga/Unit', 'Nilai']];
        foreach ($stok['detail'] ?? [] as $s) {
            $stokRows[] = [$s['lokasi'], $s['kode'], $s['nama'], $s['satuan'], $s['qty'], $s['harga'], $s['nilai']];
        }

        return [
            new ClosingSheet('Ringkasan', $ringkasan, $this->namaUser, false, ['B']),
            new ClosingSheet('Kas', $kasRows, $this->namaUser, true, ['D', 'E', 'F', 'G']),
            new ClosingSheet('Pemasukan & Pengeluaran', $keuRows, $this->namaUser, true, ['C']),
            new ClosingSheet('Top Produk', $produkRows, $this->namaUser, true, ['D', 'E', 'F']),
            new ClosingSheet('Stok Akhir', $stokRows, $this->namaUser, true, ['F', 'G']),
        ];
    }
}

class ClosingSheet implements FromArray, WithTitle, WithStyles
{
    use HasLaporanStyles;

    public function __construct(
        private string $judul,
        private array $rows,
        private ?string $namaUser,
        private bool $punyaHeader,
        private array $kolomRupiah = [],
    ) {
    }

    public function title(): string
    {
        return $this->judul;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        if ($this->punyaHeader) {
            $this->styleHeaderRow($sheet, 'A1:G1');
        } else {
            $sheet->getStyle('A:A')->getFont()->setBold(true);
        }
        foreach ($this->kolomRupiah as $kol) {
            $this->styleRupiahColumn($sheet, $kol);
        }
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisFooter = count($this->rows) + 2;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
