<?php

namespace App\Http\Controllers;

use App\Services\LaporanBarangKasirService;
use App\Support\CabangLaporan;
use App\Support\PeriodeLaporan;
use Illuminate\Http\Request;

/**
 * 4.49 (porting erp-dimsum 4.46) Tahap 3 — Laporan Penjualan Kategori di menu Penjualan (bisa dibuka
 * kasir): qty, penjualan, diskon, pendapatan per kategori; klik kategori untuk
 * melihat produk di dalamnya. Angka dihitung dari Laporan Produk yang sama.
 * Modal & keuntungan hanya untuk izin penjualan.laporan.keuntungan.
 */
class LaporanKategoriKasirController extends Controller
{
    public function index(Request $request, LaporanBarangKasirService $svc)
    {
        abort_unless(auth()->user()->can('penjualan.laporan_kategori.view'), 403);

        $cabangId = CabangLaporan::id();
        $lihatUntung = auth()->user()->can(LaporanTransaksiKasirController::IZIN_KEUNTUNGAN);
        $periode = PeriodeLaporan::baca($request, $cabangId);

        $urutBoleh = $lihatUntung ? ['qty', 'pendapatan', 'keuntungan', 'nama'] : ['qty', 'pendapatan', 'nama'];
        $urut = in_array($request->urut, $urutBoleh, true) ? $request->urut : 'pendapatan';

        $baris = LaporanBarangKasirService::urutkan($svc->kategori($periode['dari'], $periode['sampai'], $cabangId), $urut, 'kategori');
        $total = LaporanBarangKasirService::total($baris);
        $biayaTambahan = $svc->biayaTambahan($periode['dari'], $periode['sampai'], $cabangId);

        if (! $lihatUntung) {
            [$baris, $total] = LaporanProdukKasirController::sembunyikanUntung($baris, $total);
        }

        $cabangNama = $cabangId ? (\App\Models\Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';
        $internal = app(\App\Services\LaporanTransaksiKasirService::class)->internalTidakDihitung($cabangId, $periode['dari'], $periode['sampai']);

        if (in_array($request->export, ['excel', 'pdf'], true)) {
            return LaporanProdukKasirController::export($request->export, 'kategori', 'Laporan Penjualan Kategori',
                ['Periode' => $periode['label'], 'Cabang' => $cabangNama], $baris, $total, $biayaTambahan, $lihatUntung, $internal);
        }

        return view('penjualan.laporan.kategori', compact('periode', 'baris', 'total', 'biayaTambahan', 'lihatUntung', 'urut', 'cabangNama', 'internal')
            + ['semuaCabang' => $cabangId === null, 'bolehLihatProduk' => auth()->user()->can('penjualan.laporan_produk.view')]);
    }
}
