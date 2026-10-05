<?php

namespace App\Exports;

use App\Exports\Concerns\HasLaporanStyles;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel Laporan Penjualan (Sprint 3 Batch 1a, 2026-09-21).
 * Sprint Analytics Laporan Penjualan (2026-10-05) — refactor jadi
 * WithMultipleSheets: Sheet 1 = Ringkasan Analytics (5 metrik), Sheet 2 =
 * Detail Transaksi (format lama tidak diubah). Konstruktor backward-compat
 * (parameter $analytics/$dari/$sampai nullable) biar caller lama tidak pecah.
 */
class LaporanPenjualanExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private Collection $orders,
        private ?string $namaUser = null,
        private ?array $analytics = null,
        private ?Carbon $dari = null,
        private ?Carbon $sampai = null,
    ) {
    }

    public function sheets(): array
    {
        $sheets = [];
        if ($this->analytics) {
            $sheets[] = new LaporanPenjualanAnalyticsSheet($this->analytics, $this->namaUser, $this->dari, $this->sampai);
        }
        $sheets[] = new LaporanPenjualanDetailSheet($this->orders, $this->namaUser);
        return $sheets;
    }
}

/**
 * Sheet 1: Ringkasan Analytics (5 metrik dalam format label-value).
 */
class LaporanPenjualanAnalyticsSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    use HasLaporanStyles;

    public function __construct(
        private array $analytics,
        private ?string $namaUser = null,
        private ?Carbon $dari = null,
        private ?Carbon $sampai = null,
    ) {
    }

    public function title(): string
    {
        return 'Ringkasan Analytics';
    }

    public function headings(): array
    {
        return ['Metrik', 'Nilai'];
    }

    public function collection(): Collection
    {
        $sa = $this->analytics;
        $rupiah = fn ($n) => $n !== null ? 'Rp ' . number_format((float) $n, 0, ',', '.') : '—';
        $tgl = fn ($t) => $t ? $t->translatedFormat('d M Y') : '-';
        $periode = $this->dari && $this->sampai
            ? $this->dari->format('d/m/Y') . ' s/d ' . $this->sampai->format('d/m/Y')
            : ($sa['periode_label'] ?? '—');

        $topValue = $sa['top_value_day']
            ? "{$rupiah($sa['top_value_day']['omzet'])} ({$sa['top_value_day']['jumlah']} tx) - {$tgl($sa['top_value_day']['tanggal'])}"
            : '—';

        $topQty = $sa['top_qty_day']
            ? "{$sa['top_qty_day']['jumlah']} tx ({$rupiah($sa['top_qty_day']['omzet'])}) - {$tgl($sa['top_qty_day']['tanggal'])}"
            : '—';

        return collect([
            ['Periode Laporan', $periode],
            ['Rata-rata Nilai per Transaksi', $rupiah($sa['avg_tx_value'] ?? null)],
            ['Rata-rata Jumlah Transaksi', $sa['avg_daily_tx'] !== null ? number_format($sa['avg_daily_tx'], 1, ',', '.') . ' tx/hari' : '—'],
            ['Hari Omzet Tertinggi', $topValue],
            ['Hari Transaksi Terbanyak', $topQty],
            ['Rata-rata Omzet Harian', $rupiah($sa['avg_daily_revenue'] ?? null)],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:B1');
        $sheet->getStyle('A2:A7')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(60);

        $barisFooter = 9;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}

/**
 * Sheet 2: Detail Transaksi (format lama, dipertahankan apa adanya).
 */
class LaporanPenjualanDetailSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use HasLaporanStyles;

    public function __construct(private Collection $orders, private ?string $namaUser = null)
    {
    }

    public function title(): string
    {
        return 'Detail Transaksi';
    }

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Cabang', 'Pelanggan', 'Tipe', 'Total Bayar', 'Status'];
    }

    public function map($order): array
    {
        /** @var Order $order */
        return [
            $order->nomor_order,
            $order->tanggal_order?->format('d/m/Y') ?? '-',
            $order->cabang?->nama_cabang ?? '-',
            $order->nama_pelanggan ?? $order->pelanggan?->nama ?? 'Umum',
            $order->tipe_order?->label() ?? '-',
            (float) $order->total_bayar,
            $order->status?->label() ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->styleHeaderRow($sheet, 'A1:G1');
        $this->styleRupiahColumn($sheet, 'F');
        $this->autoSizeAllColumns($sheet, 'A', 'G');

        $barisFooter = $this->orders->count() + 3;
        $sheet->setCellValue('A' . $barisFooter, $this->footerDicetakOleh($this->namaUser));
        $sheet->getStyle('A' . $barisFooter)->getFont()->setItalic(true)->setSize(9);

        return [];
    }
}
