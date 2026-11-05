<?php

namespace App\Http\Controllers;

use App\Exports\LaporanTransaksiKasirExport;
use App\Models\Cabang;
use App\Services\LaporanTransaksiKasirService;
use App\Support\CabangLaporan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 4.49 (porting erp-dimsum 4.46) — Laporan Transaksi di menu Penjualan (bisa
 * dibuka kasir). Data ikut cabang aktif di header (CabangLaporan::id(); null
 * = Semua Cabang, hanya role akses semua cabang). Keuntungan hanya untuk izin
 * penjualan.laporan.keuntungan — tidak dikirim ke view/export sama sekali
 * kalau tidak berhak.
 */
class LaporanTransaksiKasirController extends Controller
{
    public const IZIN_KEUNTUNGAN = 'penjualan.laporan.keuntungan';

    public function index(Request $request, LaporanTransaksiKasirService $svc)
    {
        abort_unless(auth()->user()->can('penjualan.laporan_transaksi.view'), 403);

        $cabangId = CabangLaporan::id();
        $lihatUntung = auth()->user()->can(self::IZIN_KEUNTUNGAN);
        $posisi = $svc->bacaPosisi($request);

        if ($posisi['level'] === 'transaksi') {
            $baris = $svc->transaksi($posisi['tanggal'], $cabangId);
            $grafik = $svc->perJam($baris);
        } else {
            $baris = $svc->rekap($posisi, $cabangId);
            $grafik = $baris;
        }
        $total = $svc->total($baris);

        if (! $lihatUntung) {
            $hapus = fn ($r) => array_diff_key($r, ['hpp' => 1, 'keuntungan' => 1]);
            $baris = $baris->map($hapus);
            $grafik = $grafik->map($hapus);
            $total = $hapus($total);
        }

        $judul = $svc->judul($posisi);
        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';
        $internal = $svc->internalTidakDihitung($cabangId, ...$svc->rentangPosisi($posisi));

        if (in_array($request->export, ['excel', 'pdf'], true)) {
            return $this->export($request->export, $posisi, $baris, $total, $judul, $cabangNama, $lihatUntung, $cabangId === null, $internal);
        }

        return view('penjualan.laporan.transaksi', [
            'posisi'      => $posisi,
            'baris'       => $baris,
            'grafik'      => $grafik,
            'total'       => $total,
            'judul'       => $judul,
            'cabangNama'  => $cabangNama,
            'semuaCabang' => $cabangId === null,
            'lihatUntung' => $lihatUntung,
            'internal'    => $internal,
            'jejak'       => $svc->jejak($posisi),
            'cepat'       => $svc->pilihanCepatAktif($posisi),
            'isian'       => $svc->isianRentang($posisi),
        ]);
    }

    private function export(string $format, array $posisi, $baris, array $total, string $judul, string $cabangNama, bool $lihatUntung, bool $semuaCabang, ?array $internal)
    {
        $nama = 'Laporan-Transaksi-' . str($judul)->before(' (')->slug() . '-' . now()->format('Ymd-His');

        if ($format === 'excel') {
            return Excel::download(
                new LaporanTransaksiKasirExport($posisi['level'], $baris, $total, $judul, $cabangNama, $lihatUntung, $semuaCabang, auth()->user()->name, $internal),
                $nama . '.xlsx'
            );
        }

        $pdf = Pdf::loadView('penjualan.laporan.transaksi-pdf', [
            'judulLaporan' => 'Laporan Transaksi',
            'filterInfo'   => ['Periode' => $judul, 'Cabang' => $cabangNama],
            'level'        => $posisi['level'],
            'baris'        => $baris,
            'total'        => $total,
            'lihatUntung'  => $lihatUntung,
            'semuaCabang'  => $semuaCabang,
            'internal'     => $internal,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($nama . '.pdf');
    }
}
