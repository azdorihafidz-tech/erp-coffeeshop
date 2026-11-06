<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\ProduksiBatch;
use App\Models\ResepBumbu;
use App\Models\ResepProduksi;
use App\Models\ReturInternal;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\ClosingService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap 4 (FASE_PRODUKSI_GEROBAK_DESIGN.md) — alur lengkap contoh Owner, semua lewat HTTP
 * seperti klik di aplikasi:
 *
 *   Gudang: biji 200.000/kg + cup 1.000 → Produksi 50 Cup Kopi Isi (modal 4.600/cup)
 *   → Kirim ke Gerobak 50 × 5.000 → Gerobak Terima
 *   → Gerobak jual 10 Kopi Susu Gula Aren (Gerobak) @15.000 (Cup Kopi Isi + susu + gula aren + pipet)
 *   → sore: retur 5 cup basi (uang kembali) → gudang Terima retur → buang lewat Penyesuaian Stok.
 */
class ProduksiKirimReturAlurTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_alur_lengkap_gudang_produksi_kirim_gerobak_jual_retur_buang(): void
    {
        $u = rand(10000, 99999);
        $mkCabang = fn ($n, $k, $t) => Cabang::create(['nama_cabang' => "{$n} {$u}", 'kode_cabang' => "{$k}{$u}", 'tipe' => $t, 'is_active' => true,
            'takeaway_aktif' => true, 'take_away_fee' => 0, 'service_charge_persen' => 0, 'antrian_produksi_aktif' => false]);
        $gudang = $mkCabang('Gudang Alur', 'GAL', 'gudang_pusat');
        $gerobak = $mkCabang('Gerobak Alur', 'GBA', 'cabang');
        foreach ([$gudang, $gerobak] as $c) {
            Kas::create(['cabang_id' => $c->id, 'nama_kas' => "Kas Tunai {$c->kode_cabang}", 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
                'saldo_awal' => 500000, 'saldo_sekarang' => 500000, 'is_active' => true]);
        }
        $mk = fn ($n, $tipe, $satuan, $harga, $jual = 0) => Item::create(['kode_item' => 'AL-' . strtoupper(substr(md5($n . $u), 0, 8)), 'nama_item' => "{$n} {$u}",
            'tipe' => $tipe, 'satuan' => $satuan, 'harga_beli_terakhir' => $harga, 'harga_jual' => $jual, 'is_active' => true]);
        $biji = $mk('Biji Kopi', 'bahan_baku', 'kg', 200000);
        $cup = $mk('Cup 12oz', 'kemasan', 'pcs', 1000);
        $cupIsi = $mk('Cup Kopi Isi', 'bahan_baku', 'pcs', 0);
        $susu = $mk('Susu UHT', 'bahan_baku', 'liter', 20000);
        $aren = $mk('Gula Aren Cair', 'bahan_baku', 'liter', 30000);
        $pipet = $mk('Pipet', 'kemasan', 'pcs', 100);
        $kirimCup = $mk('Cup Kopi Isi (kirim ke cabang)', 'produk_jual', 'pcs', 0, 5000);
        $kopiGerobak = $mk('Kopi Susu Gula Aren (Gerobak)', 'produk_jual', 'gelas', 0, 15000);

        $owner = $this->buatUser('owner');
        $this->actingAs($owner);
        $stok = app(StokService::class);
        $stok->masuk($biji->id, $gudang->id, 2, 'PO', 'uji', null, 200000);
        $stok->masuk($cup->id, $gudang->id, 100, 'PO', 'uji', null, 1000);
        $stok->masuk($susu->id, $gerobak->id, 2, 'stok', 'uji', null, 20000);
        $stok->masuk($aren->id, $gerobak->id, 1, 'stok', 'uji', null, 30000);
        $stok->masuk($pipet->id, $gerobak->id, 100, 'stok', 'uji', null, 100);
        $komposisi = function (Item $produk, array $baris) use ($u) {
            $r = ResepBumbu::create(['nama' => 'Resep ' . $produk->nama_item, 'kode' => 'R' . substr(md5($produk->nama_item), 0, 8), 'item_id' => $produk->id, 'is_active' => true]);
            foreach ($baris as $i => [$item, $qty, $sat]) {
                $r->items()->create(['item_id' => $item->id, 'qty_per_unit' => $qty, 'satuan' => $sat, 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => $i]);
            }
        };
        $komposisi($kirimCup, [[$cupIsi, 1, 'pcs']]);
        $komposisi($kopiGerobak, [[$cupIsi, 1, 'pcs'], [$susu, 100, 'ml'], [$aren, 20, 'ml'], [$pipet, 1, 'pcs']]);
        $q = fn (Item $i, Cabang $c) => (float) Stock::where('item_id', $i->id)->where('lokasi_id', $c->id)->value('qty');
        $kas = fn (Cabang $c) => (float) Kas::where('cabang_id', $c->id)->where('default_untuk', 'tunai')->value('saldo_sekarang');

        // 1. Gudang: Resep Produksi + Produksi 50 Cup Kopi Isi
        $this->post(route('produksi.resep.store'), ['item_id' => $cupIsi->id, 'qty_hasil' => 1, 'is_active' => 1, 'bahan' => [
            ['item_id' => $biji->id, 'qty' => 18, 'satuan' => 'g'], ['item_id' => $cup->id, 'qty' => 1, 'satuan' => 'pcs']]])->assertSessionHas('success');
        $resep = ResepProduksi::where('item_id', $cupIsi->id)->firstOrFail();
        $this->post(route('produksi.store'), ['cabang_id' => $gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => today()->toDateString(),
            'qty_rencana' => 50, 'qty_hasil' => 50])->assertSessionHas('success');
        $prd = ProduksiBatch::where('item_id', $cupIsi->id)->firstOrFail();
        $this->assertEqualsWithDelta(230000, (float) $prd->biaya_bahan, 0.01, '0,9 kg × 200.000 + 50 × 1.000');
        $this->assertEqualsWithDelta(4600, (float) $prd->modal_per_unit, 0.01);
        $this->assertEqualsWithDelta(1.1, $q($biji, $gudang), 0.0001);
        $this->assertEqualsWithDelta(50, $q($cupIsi, $gudang), 0.0001);

        // 2. Gudang kirim 50 ke gerobak lewat POS
        $this->withSession(['active_cabang_id' => $gudang->id])->postJson(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'customer_type' => 'internal', 'outlet_tujuan_id' => $gerobak->id,
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 250000, 'payments' => [['metode' => 'tunai', 'jumlah' => 250000]],
            'items' => [['item_id' => $kirimCup->id, 'nama_item' => $kirimCup->nama_item, 'qty' => 50, 'harga_satuan' => 5000, 'satuan' => 'pcs']],
        ])->assertOk();
        $kiriman = Order::where('cabang_id', $gudang->id)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0, $q($cupIsi, $gudang), 0.0001);

        // 3. Gerobak terima
        $kasirGerobak = $this->buatUser('kasir', $gerobak->id);
        $this->actingAs($kasirGerobak)->post(route('kiriman.terima', $kiriman))->assertSessionHas('success');
        $this->assertEqualsWithDelta(50, $q($cupIsi, $gerobak), 0.0001);
        $this->assertEqualsWithDelta(230000, (float) $kiriman->fresh()->items->sum('hpp'), 0.01, 'HPP kiriman = modal produksi');

        // 4. Gerobak jual 10 Kopi Susu Gula Aren
        $this->actingAs($kasirGerobak)->withSession(['active_cabang_id' => $gerobak->id])->postJson(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 150000,
            'payments' => [['metode' => 'tunai', 'jumlah' => 150000]],
            'items' => [['item_id' => $kopiGerobak->id, 'nama_item' => $kopiGerobak->nama_item, 'qty' => 10, 'harga_satuan' => 15000, 'satuan' => 'gelas']],
        ])->assertOk();
        $jual = Order::where('cabang_id', $gerobak->id)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(40, $q($cupIsi, $gerobak), 0.0001);
        $this->assertEqualsWithDelta(1.0, $q($susu, $gerobak), 0.0001);
        $this->assertEqualsWithDelta(0.8, $q($aren, $gerobak), 0.0001);
        $this->assertEqualsWithDelta(90, $q($pipet, $gerobak), 0.0001);
        $this->assertEqualsWithDelta(77000, (float) $jual->items->sum('hpp'), 0.01, '10 × (5.000 + 2.000 + 600 + 100)');

        // 5. Retur 5 cup basi, uang kembali → gudang terima → buang lewat Penyesuaian Stok
        $row = $kiriman->kirimanItems()->first();
        $this->actingAs($kasirGerobak)->post(route('retur-cabang.store', $kiriman), ['qty' => [$row->id => 5], 'alasan' => 'basi', 'uang_kembali' => 1])->assertSessionHas('success');
        $retur = ReturInternal::where('order_id', $kiriman->id)->firstOrFail();
        $this->actingAs($this->buatUser('admin_gudang', $gudang->id))->post(route('retur-cabang.terima', $retur))->assertSessionHas('success');
        $this->assertEqualsWithDelta(5, $q($cupIsi, $gudang), 0.0001);
        $this->actingAs($owner)->post(route('stok.adjustment.store'), [
            'item_id' => $cupIsi->id, 'lokasi_id' => $gudang->id, 'qty_fisik' => 0, 'alasan' => 'rusak', 'catatan' => 'cup basi retur', 'mode_distribusi' => 'fifo',
        ])->assertRedirect();

        // Akhir: stok
        $this->assertEqualsWithDelta(0, $q($cupIsi, $gudang), 0.0001);
        $this->assertEqualsWithDelta(35, $q($cupIsi, $gerobak), 0.0001);
        $this->assertEqualsWithDelta(50, $q($cup, $gudang), 0.0001);
        // Akhir: kas
        $this->assertSame(500000.0 + 250000 - 25000, $kas($gudang));
        $this->assertSame(500000.0 - 250000 + 150000 + 25000, $kas($gerobak));
        // Akhir: stok = batch FIFO (kartu stok konsisten) di semua barang & lokasi yang tersentuh
        foreach ([$biji, $cup, $cupIsi, $susu, $aren, $pipet] as $it) {
            foreach ([$gudang, $gerobak] as $c) {
                $batch = (float) StockBatch::where('item_id', $it->id)->where('lokasi_id', $c->id)->whereNull('deleted_at')->sum('qty_sisa');
                $this->assertEqualsWithDelta($q($it, $c), $batch, 0.001, "stok = batch {$it->nama_item} @ {$c->nama_cabang}");
            }
        }

        // Laporan Transaksi gudang hari ini: 250.000 − 25.000; untung (250.000 − 230.000) − (25.000 − 23.000)
        $tot = $this->actingAs($owner)->withSession(['active_cabang_id' => $gudang->id])
            ->get(route('penjualan.laporan.transaksi', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]))->viewData('total');
        $this->assertEqualsWithDelta(225000, (float) $tot['pendapatan'], 0.01);
        $this->assertEqualsWithDelta(18000, (float) $tot['keuntungan'], 0.01);
        // Gerobak: 150.000, untung 73.000
        $tot = $this->actingAs($owner)->withSession(['active_cabang_id' => $gerobak->id])
            ->get(route('penjualan.laporan.transaksi', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]))->viewData('total');
        $this->assertEqualsWithDelta(150000, (float) $tot['pendapatan'], 0.01);
        $this->assertEqualsWithDelta(73000, (float) $tot['keuntungan'], 0.01);

        // Closing gudang: omzet 225.000, HPP 207.000, beban buang barang 23.000 (5 × 4.600)
        $cl = app(ClosingService::class)->hitung($gudang->id, today()->startOfDay(), today()->endOfDay());
        $this->assertEqualsWithDelta(225000, $cl['laba_rugi']['omzet'], 0.01);
        $this->assertEqualsWithDelta(207000, $cl['laba_rugi']['hpp'], 0.01);
        $this->assertEqualsWithDelta(23000, $cl['laba_rugi']['beban'], 0.01);
        // Closing gerobak: pembelian internal bersih 250.000 − 25.000 (arus kas, bukan beban)
        $cl = app(ClosingService::class)->hitung($gerobak->id, today()->startOfDay(), today()->endOfDay());
        $this->assertEqualsWithDelta(150000, $cl['laba_rugi']['omzet'], 0.01);
        $this->assertEqualsWithDelta(225000, $cl['keuangan']['mutasi']['pembelian_internal'], 0.01, 'pembelian dari gudang 250.000 − uang retur 25.000');
        $this->assertEqualsWithDelta(0, $cl['laba_rugi']['beban'], 0.01, 'pembelian dari gudang bukan beban');
        // Konsolidasi: penjualan gudang→gerobak (termasuk koreksi retur) dieliminasi
        $cl = app(ClosingService::class)->hitung(null, today()->startOfDay(), today()->endOfDay());
        $this->assertEqualsWithDelta(225000, $cl['laba_rugi']['eliminasi_omzet'] - $this->eliminasiLain($cl), 0.01);

        // Semua halaman yang terlibat terbuka normal
        foreach ([route('produksi.index'), route('produksi.show', $prd), route('kiriman.index'), route('kiriman.show', $kiriman),
                  route('retur-cabang.index'), route('retur-cabang.show', $retur), route('penjualan.show', $kiriman), route('stok.index')] as $url) {
            $this->actingAs($owner)->withSession(['active_cabang_id' => null])->get($url)->assertOk();
        }
    }

    /** Penjualan internal lain di DB test pada hari yang sama (data seeder), supaya assert tidak rapuh. */
    private function eliminasiLain(array $cl): float
    {
        return (float) Order::terhitung()->where('customer_type', 'internal')->whereDate('tanggal_order', today())
            ->whereNotIn('cabang_id', Cabang::where('kode_cabang', 'like', 'GAL%')->pluck('id'))->sum('total_bayar');
    }
}
