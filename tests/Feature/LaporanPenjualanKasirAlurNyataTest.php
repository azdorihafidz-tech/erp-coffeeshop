<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Meja;
use App\Models\Stock;
use App\Services\BillService;
use App\Services\PenjualanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap 4 — cek silang 3 laporan baru dengan transaksi NYATA (dibuat
 * lewat PenjualanService::buatOrder seperti POS, dan bill meja dibayar lewat
 * BillService::closeBill seperti Layout Meja/QR): diskon, service charge,
 * take away fee, bill belum dibayar. Plus: PDF kasir tanpa keuntungan.
 */
class LaporanPenjualanKasirAlurNyataTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->item = Item::where('kode_item', 'NKP-005')->first() ?? $this->markTestSkipped('Item NKP-005 tidak ada di DB test.');
        $this->c = Cabang::create([
            'nama_cabang' => 'Outlet Alur ' . uniqid(), 'kode_cabang' => 'AL' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true,
            'service_charge_persen' => 10, 'take_away_fee' => 2000,
        ]);
        Kas::create(['cabang_id' => $this->c->id, 'nama_kas' => 'Kas Tunai Alur', 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        Stock::updateOrCreate(['item_id' => $this->item->id, 'lokasi_id' => $this->c->id], ['qty' => 100, 'qty_minimum' => 0]);
    }

    private function buatOrder(string $tipe, int $qty, float $diskon, int $kasirId): \App\Models\Order
    {
        $harga = (float) $this->item->harga_jual;
        $sub = max(0, $harga * $qty - $diskon);
        $total = $sub + round($sub * 0.10, 2) + ($tipe === 'takeaway' ? 2000 : 0);

        return app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasirId, 'tipe_order' => 'penjualan', 'tipe_transaksi' => $tipe, 'diskon' => $diskon,
            'nomor_meja' => $tipe === 'dine_in' ? '5' : null,
            'items' => [['item_id' => $this->item->id, 'qty' => $qty, 'harga_satuan' => $harga, 'satuan' => $this->item->satuan, 'nama_item' => $this->item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $total]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total,
        ], $this->c->id);
    }

    public function test_total_ketiga_laporan_cocok_dengan_transaksi_nyata(): void
    {
        $kasir = $this->buatUser('kasir', $this->c->id);
        $this->actingAs($kasir);
        $harga = (float) $this->item->harga_jual;

        $o1 = $this->buatOrder('takeaway', 3, 1000, $kasir->id);   // diskon + service + take away fee
        $o2 = $this->buatOrder('dine_in', 2, 0, $kasir->id);       // service charge saja

        // Bill meja: 1 dibayar (closeBill → order), 1 masih terbuka (tidak dihitung)
        $bill = app(BillService::class);
        $mejaA = Meja::create(['cabang_id' => $this->c->id, 'nomor_meja' => '91', 'nama_meja' => 'Meja 91', 'qr_token' => Meja::generateQrToken()]);
        $mejaB = Meja::create(['cabang_id' => $this->c->id, 'nomor_meja' => '92', 'nama_meja' => 'Meja 92', 'qr_token' => Meja::generateQrToken()]);
        $b1 = $bill->tandaiMejaTerisi($mejaA->id, $kasir->id);
        $bill->addItemManual($b1->id, $this->item->id, 4, null, null, $kasir->id);
        $b1->update(['diskon' => 500]);
        $sub = $harga * 4 - 500;
        $bill->closeBill($b1->id, ['payments' => [['metode' => 'tunai', 'jumlah' => $sub + round($sub * 0.10, 2)]]], $kasir->id);
        $b2 = $bill->tandaiMejaTerisi($mejaB->id, $kasir->id);
        $bill->addItemManual($b2->id, $this->item->id, 7, null, null, $kasir->id);

        $owner = $this->buatUser('owner');
        $sesi = fn () => $this->actingAs($owner)->withSession(['active_cabang_id' => $this->c->id]);
        $trx = $sesi()->get(route('penjualan.laporan.transaksi'))->assertOk();
        $prod = $sesi()->get(route('penjualan.laporan.produk'))->assertOk();
        $kat = $sesi()->get(route('penjualan.laporan.kategori'))->assertOk();

        $this->assertSame(3, $trx->viewData('total')['transaksi'], '2 order POS + 1 bill dibayar; bill terbuka tidak dihitung');
        $produk = $prod->viewData('baris')->firstWhere('nama', $this->item->nama_item);
        $this->assertSame(9.0, $produk['qty'], 'qty 3 + 2 + 4 (bill terbuka 7 tidak dihitung)');
        $this->assertSame(1500.0, $produk['diskon'], 'diskon order 1.000 + diskon bill 500');

        $pendapatanTrx = $trx->viewData('total')['pendapatan'];
        $this->assertEqualsWithDelta($pendapatanTrx, $prod->viewData('total')['pendapatan'] + $prod->viewData('biayaTambahan'), 0.01, 'Produk + biaya = Transaksi');
        $this->assertEqualsWithDelta($pendapatanTrx, $kat->viewData('total')['pendapatan'] + $kat->viewData('biayaTambahan'), 0.01, 'Kategori + biaya = Transaksi');
        $this->assertEqualsWithDelta($trx->viewData('total')['keuntungan'], $prod->viewData('total')['keuntungan'] + $prod->viewData('biayaTambahan'), 0.01);
        $this->assertGreaterThan(0, $prod->viewData('biayaTambahan'), 'service charge + take away fee terbaca');
        $this->assertEqualsWithDelta((float) $o1->total_bayar + (float) $o2->total_bayar + $sub + round($sub * 0.10, 2), $pendapatanTrx, 0.01);
    }

    public function test_pdf_kasir_tidak_memuat_keuntungan(): void
    {
        $kasir = $this->buatUser('kasir', $this->c->id);
        $this->actingAs($kasir);
        $this->buatOrder('takeaway', 2, 0, $kasir->id);

        // Isi HTML yang dirender dompdf (PDF terkompresi tidak bisa dibaca langsung)
        $tangkap = [];
        \Illuminate\Support\Facades\View::composer(['penjualan.laporan.transaksi-pdf', 'penjualan.laporan.barang-pdf'],
            function ($view) use (&$tangkap) { $tangkap[$view->name()] = $view->getData(); });

        foreach (['penjualan.laporan.transaksi' => ['tahun' => now()->year], 'penjualan.laporan.produk' => [], 'penjualan.laporan.kategori' => []] as $r => $p) {
            $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->c->id])->get(route($r, $p + ['export' => 'pdf']))->assertOk();
        }

        $this->assertCount(2, $tangkap);
        foreach ($tangkap as $nama => $data) {
            $this->assertFalse($data['lihatUntung'], $nama);
            $html = view($nama, $data)->render();
            foreach (['Keuntungan', 'Modal (HPP)', 'Margin'] as $kata) {
                $this->assertStringNotContainsString($kata, $html, "{$nama}: {$kata}");
            }
            $this->assertArrayNotHasKey('keuntungan', $data['total']);
        }
    }
}
