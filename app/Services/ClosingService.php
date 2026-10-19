<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\ClosingPeriode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sprint Closing Periode (2026-10-17) — hitung & simpan snapshot closing.
 *
 * Prinsip klasifikasi (dipakai konsisten di semua bagian):
 * - Omzet & HPP diambil dari orders/order_items (HPP sudah tersimpan per
 *   item saat jual). Order Pending (bill belum dibayar) & Dibatalkan TIDAK
 *   dihitung, tapi dilaporkan sebagai info/peringatan.
 * - TransaksiKeuangan dengan referensi_type='order' = cerminan kas dari
 *   penjualan → hanya masuk bagian Kas, TIDAK dihitung ulang sbg pemasukan.
 * - Setoran / Transfer Antar Kas / Setoran Kasir = MUTASI internal (bukan
 *   pemasukan/pengeluaran). Setoran yang belum diterima = "Dalam Perjalanan".
 * - Pembelian persediaan (PBB/PO/pembelian internal dari Roastery), aset,
 *   retur beli = arus kas, BUKAN beban (biaya bahan sudah masuk lewat HPP).
 * - Konsolidasi: penjualan internal Roastery → outlet dieliminasi
 *   (omzet & HPP-nya dikeluarkan) supaya tidak terhitung dobel.
 */
class ClosingService
{
    public const KODE_MUTASI = ['SETOR-IN', 'SETOR-OUT', 'MUTASI-IN', 'MUTASI-OUT', 'SETORKSR-IN', 'SETORKSR-OUT'];
    public const KODE_PERSEDIAAN_ASET = ['PBB', 'ASET', 'RETUR-BELI'];
    public const BATAS_HARI = 366;

    public function __construct(private ClosingLockService $lock)
    {
    }

    // =====================================================================
    // PERHITUNGAN SNAPSHOT
    // =====================================================================

    public function hitung(?int $cabangId, Carbon $mulai, Carbon $selesai): array
    {
        $mulai = $mulai->copy()->startOfDay();
        $selesai = $selesai->copy()->startOfDay();
        $konsolidasi = $cabangId === null;
        $ids = $this->cabangIdsCakupan($cabangId);

        $penjualan = $this->hitungPenjualan($ids, $mulai, $selesai);
        $keuangan = $this->hitungTransaksiKeuangan($ids, $mulai, $selesai);
        $kas = $this->hitungKas($ids, $mulai, $selesai);
        $stok = $this->hitungStok($ids, $selesai);

        // Laba rugi
        $eliminasiOmzet = $konsolidasi ? $penjualan['internal']['omzet'] : 0.0;
        $eliminasiHpp = $konsolidasi ? $penjualan['internal']['hpp'] : 0.0;
        $omzet = $penjualan['omzet_bersih'] - $eliminasiOmzet;
        $hpp = $penjualan['hpp'] - $eliminasiHpp;
        $labaKotor = $omzet - $hpp;
        $labaBersih = $labaKotor + $keuangan['total_pemasukan_lain'] - $keuangan['total_beban'];

        $labaRugi = [
            'omzet_bersih'          => $penjualan['omzet_bersih'],
            'eliminasi_omzet'       => $eliminasiOmzet,
            'omzet'                 => $omzet,
            'hpp_penjualan'         => $penjualan['hpp'],
            'eliminasi_hpp'         => $eliminasiHpp,
            'hpp'                   => $hpp,
            'laba_kotor'            => $labaKotor,
            'margin_kotor'          => $omzet > 0 ? round($labaKotor / $omzet * 100, 1) : null,
            'pemasukan_lain'        => $keuangan['total_pemasukan_lain'],
            'beban'                 => $keuangan['total_beban'],
            'laba_bersih'           => $labaBersih,
            'margin_bersih'         => $omzet > 0 ? round($labaBersih / $omzet * 100, 1) : null,
        ];

        $data = [
            'meta' => [
                'konsolidasi'    => $konsolidasi,
                'cabang_id'      => $cabangId,
                'cabang'         => DB::table('cabangs')->whereIn('id', $ids)->orderBy('id')->pluck('nama_cabang')->all(),
                'tanggal_mulai'  => $mulai->toDateString(),
                'tanggal_selesai'=> $selesai->toDateString(),
                'jumlah_hari'    => (int) round($mulai->diffInDays($selesai)) + 1,
                'dihitung_pada'  => now()->toDateTimeString(),
            ],
            'penjualan'  => $penjualan,
            'laba_rugi'  => $labaRugi,
            'keuangan'   => $keuangan,
            'kas'        => $kas,
            'stok'       => $stok,
        ];

        $data['peringatan'] = $this->peringatan($data, $ids, $cabangId, $mulai, $selesai);

        return $data;
    }

    public function cabangIdsCakupan(?int $cabangId): array
    {
        if ($cabangId !== null) {
            return [$cabangId];
        }

        return DB::table('cabangs')->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function ordersValid(array $ids, Carbon $mulai, Carbon $selesai)
    {
        return DB::table('orders')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.cabang_id', $ids)
            ->whereBetween('orders.tanggal_order', [$mulai->toDateString(), $selesai->toDateString()])
            ->whereNotIn('orders.status', ['dibatalkan', 'pending']);
    }

    private function hitungPenjualan(array $ids, Carbon $mulai, Carbon $selesai): array
    {
        $agg = $this->ordersValid($ids, $mulai, $selesai)
            ->selectRaw('COUNT(*) as jml, COALESCE(SUM(total_harga),0) as kotor, COALESCE(SUM(diskon),0) as diskon,
                COALESCE(SUM(service_charge),0) as sc, COALESCE(SUM(take_away_fee),0) as taf, COALESCE(SUM(total_bayar),0) as bersih')
            ->first();

        $hpp = (float) DB::table('order_items')
            ->joinSub($this->ordersValid($ids, $mulai, $selesai)->select('orders.id'), 'o', 'o.id', '=', 'order_items.order_id')
            ->sum('order_items.hpp');

        $perTipe = $this->ordersValid($ids, $mulai, $selesai)
            ->selectRaw("COALESCE(tipe_transaksi, '-') as kunci, COUNT(*) as jml, COALESCE(SUM(total_bayar),0) as total")
            ->groupBy(DB::raw("COALESCE(tipe_transaksi, '-')"))
            ->get()
            ->map(fn ($r) => ['label' => $this->labelTipeTransaksi($r->kunci), 'jumlah' => (int) $r->jml, 'total' => (float) $r->total])
            ->values()->all();

        $perKanal = $this->ordersValid($ids, $mulai, $selesai)
            ->selectRaw("COALESCE(customer_type, 'retail') as kunci, COUNT(*) as jml, COALESCE(SUM(total_bayar),0) as total")
            ->groupBy(DB::raw("COALESCE(customer_type, 'retail')"))
            ->get()
            ->map(fn ($r) => ['label' => ucfirst($r->kunci), 'kunci' => $r->kunci, 'jumlah' => (int) $r->jml, 'total' => (float) $r->total])
            ->values()->all();

        // Metode bayar: order_payments (split payment); order lama tanpa
        // baris payment memakai orders.tipe_pembayaran.
        $metode = [];
        $payRows = DB::table('order_payments')
            ->joinSub($this->ordersValid($ids, $mulai, $selesai)->select('orders.id'), 'o', 'o.id', '=', 'order_payments.order_id')
            ->whereNull('order_payments.deleted_at')
            ->selectRaw('order_payments.metode as metode, COALESCE(SUM(order_payments.jumlah),0) as total')
            ->groupBy('order_payments.metode')
            ->get();
        foreach ($payRows as $r) {
            $metode[$r->metode] = ($metode[$r->metode] ?? 0) + (float) $r->total;
        }
        $tanpaPayment = $this->ordersValid($ids, $mulai, $selesai)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('order_payments')
                    ->whereColumn('order_payments.order_id', 'orders.id')
                    ->whereNull('order_payments.deleted_at');
            })
            ->selectRaw('tipe_pembayaran as metode, COALESCE(SUM(total_bayar),0) as total')
            ->groupBy('tipe_pembayaran')
            ->get();
        foreach ($tanpaPayment as $r) {
            $k = $r->metode ?: 'tunai';
            $metode[$k] = ($metode[$k] ?? 0) + (float) $r->total;
        }
        $perMetode = collect($metode)->map(fn ($total, $k) => ['label' => $this->labelMetode($k), 'total' => $total])->values()->all();

        $internalOrders = $this->ordersValid($ids, $mulai, $selesai)->where('customer_type', 'internal');
        $internalOmzet = (float) (clone $internalOrders)->sum('total_bayar');
        $internalHpp = (float) DB::table('order_items')
            ->joinSub((clone $internalOrders)->select('orders.id'), 'o', 'o.id', '=', 'order_items.order_id')
            ->sum('order_items.hpp');

        $topProduk = DB::table('order_items')
            ->joinSub($this->ordersValid($ids, $mulai, $selesai)->select('orders.id'), 'o', 'o.id', '=', 'order_items.order_id')
            ->selectRaw('order_items.nama_item as nama, SUM(order_items.qty) as qty, MAX(order_items.satuan) as satuan,
                COALESCE(SUM(order_items.total_harga),0) as omzet, COALESCE(SUM(order_items.hpp),0) as hpp')
            ->groupBy('order_items.nama_item')
            ->orderByDesc('omzet')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'nama' => $r->nama, 'qty' => (float) $r->qty, 'satuan' => $r->satuan,
                'omzet' => (float) $r->omzet, 'hpp' => (float) $r->hpp,
            ])->all();

        $base = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('cabang_id', $ids)
            ->whereBetween('tanggal_order', [$mulai->toDateString(), $selesai->toDateString()]);
        $pending = (clone $base)->where('status', 'pending')->selectRaw('COUNT(*) as jml, COALESCE(SUM(total_bayar),0) as total')->first();
        $batal = (clone $base)->where('status', 'dibatalkan')->selectRaw('COUNT(*) as jml, COALESCE(SUM(total_bayar),0) as total')->first();

        $jml = (int) $agg->jml;
        $bersih = (float) $agg->bersih;

        return [
            'jumlah_transaksi' => $jml,
            'omzet_kotor'      => (float) $agg->kotor,
            'diskon'           => (float) $agg->diskon,
            'service_charge'   => (float) $agg->sc,
            'take_away_fee'    => (float) $agg->taf,
            'omzet_bersih'     => $bersih,
            'rata_rata'        => $jml > 0 ? round($bersih / $jml, 2) : 0,
            'hpp'              => $hpp,
            'per_tipe'         => $perTipe,
            'per_kanal'        => $perKanal,
            'per_metode'       => $perMetode,
            'internal'         => ['omzet' => $internalOmzet, 'hpp' => $internalHpp],
            'top_produk'       => $topProduk,
            'pending'          => ['jumlah' => (int) $pending->jml, 'total' => (float) $pending->total],
            'dibatalkan'       => ['jumlah' => (int) $batal->jml, 'total' => (float) $batal->total],
        ];
    }

    /**
     * Golongan 1 baris transaksi keuangan — lihat catatan class.
     */
    public function golongan(object $r): string
    {
        $kode = $r->kode ?? null;
        $ref = $r->referensi_type ?? null;
        $enum = $r->kategori ?? null;

        if ($ref === 'order') {
            return 'penjualan';
        }
        if ($kode === 'SALDO' || $enum === 'saldo_awal') {
            return 'saldo_awal';
        }
        if (in_array($kode, self::KODE_MUTASI, true) || in_array($ref, ['transfer_antar_kas', 'setoran_kasir'], true)) {
            return 'mutasi';
        }
        if ($ref === 'order_internal') {
            return 'pembelian_internal';
        }
        if (in_array($kode, self::KODE_PERSEDIAAN_ASET, true)
            || ($kode === null && in_array($enum, ['pembelian_bahan', 'pembelian_aset'], true))
            || $ref === 'purchase_order') {
            return 'persediaan_aset';
        }

        return 'operasional';
    }

    private function hitungTransaksiKeuangan(array $ids, Carbon $mulai, Carbon $selesai): array
    {
        $rows = DB::table('transaksi_keuangans as tk')
            ->leftJoin('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
            ->whereNull('tk.deleted_at')
            ->whereIn('tk.cabang_id', $ids)
            ->whereBetween('tk.tanggal_transaksi', [$mulai->toDateString(), $selesai->toDateString()])
            ->select('tk.id', 'tk.cabang_id', 'tk.tipe', 'tk.kategori', 'tk.referensi_type', 'tk.jumlah',
                'tk.status_setoran', 'tk.setoran_pair_id', 'kt.kode', 'kt.nama as kt_nama')
            ->get();

        $pemasukanLain = [];
        $beban = [];
        $persediaan = [];
        $mutasi = [
            'setoran_keluar_diterima' => 0.0, 'setoran_keluar_menunggu' => 0.0, 'setoran_keluar_jumlah' => 0,
            'setoran_masuk_diterima'  => 0.0, 'setoran_masuk_menunggu'  => 0.0, 'setoran_masuk_jumlah'  => 0,
            'transfer_antar_kas'      => 0.0, 'transfer_antar_kas_jumlah' => 0,
            'setoran_kasir_keluar'    => 0.0, 'setoran_kasir_masuk' => 0.0,
            'pembelian_internal'      => 0.0,
        ];

        foreach ($rows as $r) {
            $jumlah = (float) $r->jumlah;
            $label = $r->kt_nama ?: $this->labelEnumKategori($r->kategori);
            $gol = $this->golongan($r);

            if ($gol === 'operasional') {
                if ($r->tipe === 'pemasukan') {
                    $pemasukanLain[$label] = ($pemasukanLain[$label] ?? 0) + $jumlah;
                } else {
                    $beban[$label] = ($beban[$label] ?? 0) + $jumlah;
                }
            } elseif ($gol === 'persediaan_aset' || $gol === 'pembelian_internal') {
                $labelP = $gol === 'pembelian_internal' ? 'Pembelian Internal dari Roastery' : $label;
                $arah = $r->tipe === 'pemasukan' ? -1 : 1;
                $persediaan[$labelP] = ($persediaan[$labelP] ?? 0) + $arah * $jumlah;
                if ($gol === 'pembelian_internal') {
                    $mutasi['pembelian_internal'] += $jumlah;
                }
            } elseif ($gol === 'mutasi') {
                $menunggu = $r->status_setoran === 'menunggu_diterima';
                switch ($r->kode) {
                    case 'SETOR-OUT':
                        $mutasi['setoran_keluar_jumlah']++;
                        $mutasi[$menunggu ? 'setoran_keluar_menunggu' : 'setoran_keluar_diterima'] += $jumlah;
                        break;
                    case 'SETOR-IN':
                        $mutasi['setoran_masuk_jumlah']++;
                        $mutasi[$menunggu ? 'setoran_masuk_menunggu' : 'setoran_masuk_diterima'] += $jumlah;
                        break;
                    case 'MUTASI-OUT':
                        $mutasi['transfer_antar_kas'] += $jumlah;
                        $mutasi['transfer_antar_kas_jumlah']++;
                        break;
                    case 'SETORKSR-OUT':
                        $mutasi['setoran_kasir_keluar'] += $jumlah;
                        break;
                    case 'SETORKSR-IN':
                        $mutasi['setoran_kasir_masuk'] += $jumlah;
                        break;
                    default:
                        if ($r->referensi_type === 'transfer_antar_kas' && $r->tipe === 'pengeluaran') {
                            $mutasi['transfer_antar_kas'] += $jumlah;
                            $mutasi['transfer_antar_kas_jumlah']++;
                        }
                }
            }
        }

        $toList = function (array $arr) {
            arsort($arr);

            return collect($arr)->map(fn ($v, $k) => ['label' => $k, 'total' => round($v, 2)])->values()->all();
        };

        return [
            'pemasukan_lain'       => $toList($pemasukanLain),
            'total_pemasukan_lain' => round(array_sum($pemasukanLain), 2),
            'beban'                => $toList($beban),
            'total_beban'          => round(array_sum($beban), 2),
            'persediaan_aset'      => $toList($persediaan),
            'total_persediaan_aset'=> round(array_sum($persediaan), 2),
            'mutasi'               => $mutasi,
            'dalam_perjalanan'     => $mutasi['setoran_keluar_menunggu'],
        ];
    }

    private function hitungKas(array $ids, Carbon $mulai, Carbon $selesai): array
    {
        $kasList = DB::table('kas')
            ->leftJoin('cabangs', 'cabangs.id', '=', 'kas.cabang_id')
            ->whereIn('kas.cabang_id', $ids)
            ->select('kas.id', 'kas.nama_kas', 'kas.tipe_kas', 'kas.default_untuk', 'kas.saldo_awal', 'kas.saldo_sekarang',
                'kas.deleted_at', 'cabangs.nama_cabang')
            ->orderBy('kas.cabang_id')->orderBy('kas.nama_kas')
            ->get();

        if ($kasList->isEmpty()) {
            return ['detail' => [], 'total_tunai' => 0, 'total_non_tunai' => 0, 'total_akhir' => 0,
                'total_awal' => 0, 'total_masuk' => 0, 'total_keluar' => 0];
        }

        $m = $mulai->toDateString();
        $s = $selesai->toDateString();
        // Setoran masuk yang belum diterima belum menambah kas (lihat SetoranController::store).
        $agg = DB::table('transaksi_keuangans as tk')
            ->leftJoin('kategori_transaksis as kt', 'kt.id', '=', 'tk.kategori_id')
            ->whereNull('tk.deleted_at')
            ->whereIn('tk.kas_id', $kasList->pluck('id'))
            ->whereRaw("NOT (COALESCE(kt.kode, '') = 'SETOR-IN' AND COALESCE(tk.status_setoran, '') = 'menunggu_diterima')")
            ->selectRaw("tk.kas_id,
                SUM(CASE WHEN tk.tanggal_transaksi < ? THEN (CASE WHEN tk.tipe = 'pemasukan' THEN tk.jumlah ELSE -tk.jumlah END) ELSE 0 END) as sebelum,
                SUM(CASE WHEN tk.tanggal_transaksi BETWEEN ? AND ? AND tk.tipe = 'pemasukan' THEN tk.jumlah ELSE 0 END) as masuk,
                SUM(CASE WHEN tk.tanggal_transaksi BETWEEN ? AND ? AND tk.tipe = 'pengeluaran' THEN tk.jumlah ELSE 0 END) as keluar,
                SUM(CASE WHEN tk.tipe = 'pemasukan' THEN tk.jumlah ELSE -tk.jumlah END) as semua,
                MAX(CASE WHEN tk.referensi_type = 'kas' AND tk.referensi_id = tk.kas_id THEN 1 ELSE 0 END) as ada_saldo_awal_trx",
                [$m, $m, $s, $m, $s])
            ->groupBy('tk.kas_id')
            ->get()
            ->keyBy('kas_id');

        $detail = [];
        foreach ($kasList as $k) {
            $a = $agg[$k->id] ?? null;
            // Pola dual-path sama dgn KeuanganController::hitungExpectedSaldoKas().
            $base = ($a && (int) $a->ada_saldo_awal_trx === 1) ? 0.0 : (float) $k->saldo_awal;
            $awal = $base + (float) ($a->sebelum ?? 0);
            $masuk = (float) ($a->masuk ?? 0);
            $keluar = (float) ($a->keluar ?? 0);
            $akhir = $awal + $masuk - $keluar;
            $hitungKini = $base + (float) ($a->semua ?? 0);

            if ($k->deleted_at && abs($awal) < 0.01 && abs($masuk) < 0.01 && abs($keluar) < 0.01) {
                continue;
            }

            $detail[] = [
                'kas_id'         => (int) $k->id,
                'nama_kas'       => $k->nama_kas . ($k->deleted_at ? ' (dihapus)' : ''),
                'cabang'         => $k->nama_cabang,
                'jenis'          => $k->tipe_kas === 'tunai' ? 'tunai' : 'non_tunai',
                'default_untuk'  => $k->default_untuk,
                'saldo_awal'     => round($awal, 2),
                'masuk'          => round($masuk, 2),
                'keluar'         => round($keluar, 2),
                'saldo_akhir'    => round($akhir, 2),
                'saldo_tercatat_kini' => (float) $k->saldo_sekarang,
                'saldo_hitung_kini'   => round($hitungKini, 2),
            ];
        }

        $sum = fn ($key, $jenis = null) => round(collect($detail)
            ->when($jenis, fn ($c) => $c->where('jenis', $jenis))
            ->sum($key), 2);

        return [
            'detail'          => $detail,
            'total_awal'      => $sum('saldo_awal'),
            'total_masuk'     => $sum('masuk'),
            'total_keluar'    => $sum('keluar'),
            'total_tunai'     => $sum('saldo_akhir', 'tunai'),
            'total_non_tunai' => $sum('saldo_akhir', 'non_tunai'),
            'total_akhir'     => $sum('saldo_akhir'),
        ];
    }

    /**
     * Posisi stok per akhir periode = stok sekarang dikoreksi mundur dengan
     * pergerakan SETELAH tanggal selesai. Movement tipe 'transfer' hanya
     * dihitung sbg keluar di lokasi asal (stok tujuan bertambah lewat
     * movement 'masuk' terpisah saat diterima — StokService). Nilai =
     * qty × rata-rata harga batch FIFO aktif (fallback harga beli terakhir).
     */
    private function hitungStok(array $ids, Carbon $selesai): array
    {
        $batas = $selesai->copy()->endOfDay()->toDateTimeString();

        $stocks = DB::table('stocks')
            ->join('items', 'items.id', '=', 'stocks.item_id')
            ->join('cabangs', 'cabangs.id', '=', 'stocks.lokasi_id')
            ->whereNull('stocks.deleted_at')
            ->whereIn('stocks.lokasi_id', $ids)
            ->select('stocks.item_id', 'stocks.lokasi_id', 'stocks.qty', 'items.kode_item', 'items.nama_item',
                'items.satuan', 'items.harga_beli_terakhir', 'cabangs.nama_cabang')
            ->get();

        $masukSetelah = DB::table('stock_movements')
            ->where('created_at', '>', $batas)
            ->whereIn('lokasi_tujuan_id', $ids)
            ->where('tipe', '!=', 'transfer')
            ->selectRaw('item_id, lokasi_tujuan_id as lokasi_id, SUM(qty) as qty')
            ->groupBy('item_id', 'lokasi_tujuan_id')
            ->get()
            ->keyBy(fn ($r) => $r->item_id . '-' . $r->lokasi_id);

        $keluarSetelah = DB::table('stock_movements')
            ->where('created_at', '>', $batas)
            ->whereIn('lokasi_asal_id', $ids)
            ->selectRaw('item_id, lokasi_asal_id as lokasi_id, SUM(qty) as qty')
            ->groupBy('item_id', 'lokasi_asal_id')
            ->get()
            ->keyBy(fn ($r) => $r->item_id . '-' . $r->lokasi_id);

        $hargaBatch = DB::table('stock_batches')
            ->whereNull('deleted_at')
            ->where('qty_sisa', '>', 0)
            ->whereIn('lokasi_id', $ids)
            ->selectRaw('item_id, lokasi_id, SUM(qty_sisa * harga_beli_per_unit) / SUM(qty_sisa) as harga')
            ->groupBy('item_id', 'lokasi_id')
            ->get()
            ->keyBy(fn ($r) => $r->item_id . '-' . $r->lokasi_id);

        $detail = [];
        foreach ($stocks as $st) {
            $key = $st->item_id . '-' . $st->lokasi_id;
            $qty = (float) $st->qty
                - (float) ($masukSetelah[$key]->qty ?? 0)
                + (float) ($keluarSetelah[$key]->qty ?? 0);
            $qty = round(max(0, $qty), 3);
            if ($qty <= 0) {
                continue;
            }
            $harga = isset($hargaBatch[$key]) ? (float) $hargaBatch[$key]->harga : (float) $st->harga_beli_terakhir;

            $detail[] = [
                'lokasi'  => $st->nama_cabang,
                'kode'    => $st->kode_item,
                'nama'    => $st->nama_item,
                'satuan'  => $st->satuan,
                'qty'     => $qty,
                'harga'   => round($harga, 2),
                'nilai'   => round($qty * $harga, 2),
            ];
        }

        usort($detail, fn ($a, $b) => [$a['lokasi'], $a['nama']] <=> [$b['lokasi'], $b['nama']]);

        $perLokasi = collect($detail)->groupBy('lokasi')->map(fn ($g, $lok) => [
            'lokasi' => $lok, 'jumlah_item' => $g->count(), 'nilai' => round($g->sum('nilai'), 2),
        ])->values()->all();

        return [
            'detail'      => $detail,
            'per_lokasi'  => $perLokasi,
            'total_nilai' => round(collect($detail)->sum('nilai'), 2),
        ];
    }

    private function peringatan(array $data, array $ids, ?int $cabangId, Carbon $mulai, Carbon $selesai): array
    {
        $w = [];
        $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

        $p = $data['penjualan']['pending'];
        if ($p['jumlah'] > 0) {
            $w[] = "Ada {$p['jumlah']} bill tersimpan belum dibayar ({$rp($p['total'])}) — tidak dihitung sebagai omzet.";
        }

        $mut = $data['keuangan']['mutasi'];
        if ($mut['setoran_keluar_menunggu'] > 0) {
            $w[] = 'Setoran keluar ' . $rp($mut['setoran_keluar_menunggu']) . ' belum dikonfirmasi penerima — dicatat sebagai "Dalam Perjalanan".';
        }
        if ($mut['setoran_masuk_menunggu'] > 0) {
            $w[] = 'Setoran masuk ' . $rp($mut['setoran_masuk_menunggu']) . ' belum dikonfirmasi — belum dihitung ke saldo kas.';
        }

        $setoranKasir = DB::table('setorans')->whereNull('deleted_at')->whereIn('cabang_id', $ids)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->where('status', 'menunggu')->count();
        if ($setoranKasir > 0) {
            $w[] = "Ada {$setoranKasir} Setoran Kasir masih menunggu approval.";
        }

        $transit = DB::table('stock_transfers')->whereNull('deleted_at')
            ->where('status', 'dikirim')
            ->where(fn ($q) => $q->whereIn('dari_lokasi_id', $ids)->orWhereIn('ke_lokasi_id', $ids))
            ->where('tanggal_kirim', '<=', $selesai->toDateString())
            ->count();
        if ($transit > 0) {
            $w[] = "Ada {$transit} transfer stok berstatus Dikirim (belum diterima) — barangnya tidak tercatat di stok lokasi mana pun.";
        }

        foreach ($data['kas']['detail'] as $k) {
            if (abs($k['saldo_tercatat_kini'] - $k['saldo_hitung_kini']) > 1) {
                $w[] = "Saldo {$k['nama_kas']} tercatat {$rp($k['saldo_tercatat_kini'])} tapi hasil hitung transaksi {$rp($k['saldo_hitung_kini'])}. Cek riwayat transaksi kas ini.";
            }
        }

        if ($selesai->isToday()) {
            $w[] = 'Periode mencakup hari ini — setelah dikunci, transaksi baru bertanggal hari ini di cakupan ini akan ditolak.';
        }

        if ($cabangId === null) {
            $belum = Cabang::query()->aktif()->orderBy('nama_cabang')->get()->filter(function ($c) use ($mulai, $selesai) {
                return !ClosingPeriode::where('status', ClosingPeriode::STATUS_FINAL)
                    ->where('cabang_id', $c->id)
                    ->where('tanggal_mulai', '<=', $mulai->toDateString())
                    ->where('tanggal_selesai', '>=', $selesai->toDateString())
                    ->exists();
            });
            if ($belum->isNotEmpty() && Cabang::query()->aktif()->count() > 1) {
                $w[] = 'Cabang yang belum closing Final untuk periode ini: ' . $belum->pluck('nama_cabang')->implode(', ') . '.';
            }
        }

        return $w;
    }

    // =====================================================================
    // SIMPAN / FINAL / BUKA KUNCI
    // =====================================================================

    public function validasiPeriode(string $jenis, ?int $cabangId, Carbon $mulai, Carbon $selesai, ?int $kecualiId = null): void
    {
        if (!array_key_exists($jenis, ClosingPeriode::JENIS)) {
            throw new InvalidArgumentException('Jenis closing tidak dikenal.');
        }
        if ($mulai->gt($selesai)) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh setelah tanggal selesai.');
        }
        if ($selesai->copy()->startOfDay()->gt(today())) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh melewati hari ini — closing hanya untuk periode yang sudah berjalan.');
        }
        if ((int) round($mulai->diffInDays($selesai)) + 1 > self::BATAS_HARI) {
            throw new InvalidArgumentException('Rentang closing maksimal ' . self::BATAS_HARI . ' hari.');
        }
    }

    /** Closing final lain dengan jenis & cakupan sama yang tanggalnya bertumpuk. */
    public function bentrokFinal(string $jenis, ?int $cabangId, Carbon $mulai, Carbon $selesai, ?int $kecualiId = null): ?ClosingPeriode
    {
        return ClosingPeriode::where('status', ClosingPeriode::STATUS_FINAL)
            ->where('jenis', $jenis)
            ->when($cabangId === null, fn ($q) => $q->whereNull('cabang_id'), fn ($q) => $q->where('cabang_id', $cabangId))
            ->where('tanggal_mulai', '<=', $selesai->toDateString())
            ->where('tanggal_selesai', '>=', $mulai->toDateString())
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->first();
    }

    public function simpan(string $jenis, ?int $cabangId, Carbon $mulai, Carbon $selesai, User $user, bool $final, ?string $catatan = null): ClosingPeriode
    {
        $this->validasiPeriode($jenis, $cabangId, $mulai, $selesai);

        return DB::transaction(function () use ($jenis, $cabangId, $mulai, $selesai, $user, $final, $catatan) {
            $closing = ClosingPeriode::where('status', ClosingPeriode::STATUS_DRAFT)
                ->where('jenis', $jenis)
                ->when($cabangId === null, fn ($q) => $q->whereNull('cabang_id'), fn ($q) => $q->where('cabang_id', $cabangId))
                ->whereDate('tanggal_mulai', $mulai->toDateString())
                ->whereDate('tanggal_selesai', $selesai->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$closing) {
                $sebelumnya = ClosingPeriode::where('status', ClosingPeriode::STATUS_DIBUKA)
                    ->where('jenis', $jenis)
                    ->when($cabangId === null, fn ($q) => $q->whereNull('cabang_id'), fn ($q) => $q->where('cabang_id', $cabangId))
                    ->whereDate('tanggal_mulai', $mulai->toDateString())
                    ->whereDate('tanggal_selesai', $selesai->toDateString())
                    ->orderByDesc('versi')
                    ->first();

                $closing = new ClosingPeriode([
                    'kode'            => $this->generateKode(),
                    'jenis'           => $jenis,
                    'tanggal_mulai'   => $mulai->toDateString(),
                    'tanggal_selesai' => $selesai->toDateString(),
                    'cabang_id'       => $cabangId,
                    'status'          => ClosingPeriode::STATUS_DRAFT,
                    'versi'           => $sebelumnya ? $sebelumnya->versi + 1 : 1,
                    'parent_id'       => $sebelumnya?->id,
                    'dibuat_oleh'     => $user->id,
                ]);
            }

            $closing->catatan = $catatan;
            $this->isiSnapshot($closing);
            $closing->save();

            if ($final) {
                $this->kunci($closing, $user);
            }

            return $closing->fresh();
        });
    }

    public function finalkan(ClosingPeriode $closing, User $user): ClosingPeriode
    {
        if ($closing->status !== ClosingPeriode::STATUS_DRAFT) {
            throw new InvalidArgumentException('Hanya closing berstatus Draft yang bisa dikunci.');
        }
        $this->validasiPeriode($closing->jenis, $closing->cabang_id, $closing->tanggal_mulai, $closing->tanggal_selesai);

        return DB::transaction(function () use ($closing, $user) {
            // Angka dihitung ulang tepat saat dikunci — draft bisa sudah basi.
            $this->isiSnapshot($closing);
            $closing->save();
            $this->kunci($closing, $user);

            return $closing->fresh();
        });
    }

    public function bukaKunci(ClosingPeriode $closing, User $user, string $alasan): ClosingPeriode
    {
        if ($closing->status !== ClosingPeriode::STATUS_FINAL) {
            throw new InvalidArgumentException('Hanya closing Final yang bisa dibuka kuncinya.');
        }

        $closing->update([
            'status'      => ClosingPeriode::STATUS_DIBUKA,
            'dibuka_oleh' => $user->id,
            'dibuka_pada' => now(),
            'alasan_buka' => $alasan,
        ]);
        $this->lock->flush();

        return $closing->fresh();
    }

    private function kunci(ClosingPeriode $closing, User $user): void
    {
        $bentrok = $this->bentrokFinal($closing->jenis, $closing->cabang_id, $closing->tanggal_mulai, $closing->tanggal_selesai, $closing->id);
        if ($bentrok) {
            throw new InvalidArgumentException(
                "Periode bertumpuk dengan closing {$closing->labelJenis()} Final {$bentrok->kode} ({$bentrok->labelPeriode()}). "
                . 'Buka kunci closing tersebut dulu bila ingin closing ulang.'
            );
        }

        $closing->update([
            'status'          => ClosingPeriode::STATUS_FINAL,
            'difinalkan_oleh' => $user->id,
            'difinalkan_pada' => now(),
        ]);
        $this->lock->flush();
    }

    private function isiSnapshot(ClosingPeriode $closing): void
    {
        $data = $this->hitung($closing->cabang_id, Carbon::parse($closing->tanggal_mulai), Carbon::parse($closing->tanggal_selesai));

        $closing->data = $data;
        $closing->total_omzet = $data['laba_rugi']['omzet'];
        $closing->total_hpp = $data['laba_rugi']['hpp'];
        $closing->laba_bersih = $data['laba_rugi']['laba_bersih'];
        $closing->total_kas_akhir = $data['kas']['total_akhir'];
        $closing->dihitung_pada = now();
    }

    private function generateKode(): string
    {
        $prefix = 'CL-' . now()->format('Ym') . '-';
        $last = ClosingPeriode::where('kode', 'like', $prefix . '%')->lockForUpdate()->orderByDesc('kode')->value('kode');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // =====================================================================
    // LABEL
    // =====================================================================

    private function labelTipeTransaksi(?string $k): string
    {
        return match ($k) {
            'dine_in'  => 'Dine-in',
            'takeaway' => 'Takeaway',
            'frozen'   => 'Frozen',
            default    => $k && $k !== '-' ? ucwords(str_replace('_', ' ', $k)) : 'Lainnya',
        };
    }

    private function labelMetode(?string $k): string
    {
        return match ($k) {
            'tunai'    => 'Tunai',
            'transfer' => 'Transfer Bank',
            'qris'     => 'QRIS',
            default    => ucfirst((string) $k),
        };
    }

    private function labelEnumKategori(?string $k): string
    {
        return \App\Enums\KategoriTransaksi::tryFrom((string) $k)?->label() ?? 'Lainnya';
    }
}
