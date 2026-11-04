<?php

namespace Tests\Feature;

use App\Enums\StatusOrder;
use App\Http\Controllers\SetoranHarianController;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Services\LaporanKonsumsiBahanService;
use App\Services\LaporanLabaRugiService;
use App\Services\PenjualanService;
use App\Services\SalesAnalyticsService;
use App\Services\SetoranHarianService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Laporan hanya menghitung order yang benar-benar terjual (bukan dihapus,
 * bukan Bill Tersimpan belum dibayar, bukan Dibatalkan) + aturan
 * "Batalkan dulu, baru Hapus" di Riwayat Order (2026-11-04).
 */
class OrderHapusBatalkanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $cabang;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabang = Cabang::create([
            'nama_cabang' => 'Cabang Test OHB' . uniqid(),
            'kode_cabang' => substr('OHB' . strtoupper(substr(uniqid(), -6)), 0, 10),
            'tipe' => 'cabang',
            'is_active' => true,
        ]);
    }

    private function order(float $total, float $hpp, string $status = 'selesai', string $tanggal = '2025-01-10'): Order
    {
        $o = Order::create([
            'cabang_id' => $this->cabang->id,
            'nomor_order' => 'TST-OHB-' . uniqid() . ++$this->seq,
            'tanggal_order' => $tanggal,
            'tipe_order' => 'produk_jadi',
            'tipe_transaksi' => 'takeaway',
            'total_harga' => $total,
            'diskon' => 0,
            'total_bayar' => $total,
            'jumlah_bayar' => $total,
            'tipe_pembayaran' => 'tunai',
            'status' => $status,
        ]);
        OrderItem::create([
            'order_id' => $o->id, 'nama_item' => 'Kopi Test', 'qty' => 1, 'satuan' => 'gelas',
            'harga_satuan' => $total, 'total_harga' => $total, 'hpp' => $hpp,
        ]);

        return $o;
    }

    /** 1 order sah + 1 sah tapi dihapus + 1 Bill Tersimpan + 1 Dibatalkan. */
    private function dataCampuran(): void
    {
        $this->order(100000, 40000);
        $this->order(500000, 1)->delete();
        $this->order(300000, 1, 'pending');
        $this->order(200000, 1, 'dibatalkan');
    }

    // ------------------------------------------------------------------ laporan

    public function test_laporan_hanya_menghitung_order_yang_sudah_dibayar(): void
    {
        $this->dataCampuran();
        $mulai = Carbon::parse('2025-01-01');
        $akhir = Carbon::parse('2025-01-31');
        $id = $this->cabang->id;

        $lr = app(LaporanLabaRugiService::class)->getRingkasan($mulai, $akhir, $id);
        $this->assertEquals(100000, $lr['total_omzet'], 'Laba Rugi: omzet');
        $this->assertEquals(40000, $lr['total_hpp'], 'Laba Rugi: HPP');
        $this->assertSame(1, $lr['total_order'], 'Laba Rugi: jumlah order');

        $kb = app(LaporanKonsumsiBahanService::class)->getRingkasan($mulai, $akhir, $id, null);
        $this->assertEquals(100000, $kb['total_omzet'], 'Konsumsi Bahan: omzet');

        $sh = app(SetoranHarianService::class);
        $this->assertSame(1, $sh->countOrder($mulai, $akhir, $id), 'Setoran Harian: jumlah order');
        $this->assertEquals(100000, collect($sh->getBreakdownPemasukan($mulai, $akhir, $id))->sum('total'), 'Setoran Harian: breakdown pemasukan');
        $this->assertCount(1, $sh->getDetailOrder($mulai, $akhir, $id), 'Setoran Harian: detail order');

        // Rekap export Setoran Harian (controller)
        $ref = new \ReflectionMethod(SetoranHarianController::class, 'buildRekapHarian');
        $rekap = collect($ref->invoke(app(SetoranHarianController::class), $mulai, $akhir, $id));
        $this->assertSame(1, (int) $rekap->sum('jumlah_order'), 'Rekap Setoran Harian: jumlah order');

        $sa = app(SalesAnalyticsService::class);
        $this->assertEquals(100000, $sa->getAvgTransactionValue($id, $mulai, $akhir->copy()->endOfDay()), 'Analytics: rata-rata transaksi');
    }

    public function test_laporan_penjualan_tidak_menampilkan_order_dihapus_dan_bill_belum_dibayar(): void
    {
        $this->dataCampuran();
        $owner = $this->buatUser('owner');

        $res = $this->actingAs($owner)->get(route('laporan.penjualan', [
            'dari' => '2025-01-01', 'sampai' => '2025-01-31', 'cabang_id' => $this->cabang->id,
        ]));
        $res->assertOk();
        $res->assertViewHas('totalOmzet', fn ($v) => (float) $v === 100000.0);
        $res->assertViewHas('totalTransaksi', 1);
    }

    // -------------------------------------------------------------------- hapus

    public function test_order_sudah_dibayar_tidak_bisa_dihapus_termasuk_owner(): void
    {
        $o = $this->order(100000, 40000);
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->from(route('penjualan.index'))
            ->delete(route('penjualan.destroy', $o))
            ->assertRedirect(route('penjualan.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Batalkan'));

        $this->assertNull($o->fresh()->deleted_at, 'Order terbayar tidak boleh terhapus.');
    }

    public function test_order_dibatalkan_dan_bill_belum_dibayar_boleh_dihapus(): void
    {
        $owner = $this->buatUser('owner');
        foreach (['dibatalkan', 'pending'] as $status) {
            $o = $this->order(50000, 1, $status);
            $this->actingAs($owner)->delete(route('penjualan.destroy', $o))
                ->assertRedirect(route('penjualan.index'))
                ->assertSessionHas('success');
            $this->assertNotNull(Order::withTrashed()->find($o->id)->deleted_at, "Order {$status} harus terhapus.");
        }
    }

    public function test_batalkan_mengembalikan_stok_dan_kas_lalu_boleh_dihapus(): void
    {
        $item = Item::where('kode_item', 'NKP-005')->first() ?? $this->markTestSkipped('Item Air Mineral (NKP-005) tidak ada di DB test.');
        $kas = Kas::create(['cabang_id' => $this->cabang->id, 'nama_kas' => 'Kas Tunai OHB', 'tipe_kas' => 'tunai',
            'default_untuk' => 'tunai', 'saldo_awal' => 100000, 'saldo_sekarang' => 100000, 'is_active' => true]);
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $this->cabang->id], ['qty' => 10, 'qty_minimum' => 0]);
        $kasir = $this->buatUser('kasir', $this->cabang->id);

        $total = (float) $item->harga_jual * 2;
        $order = app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'items' => [['item_id' => $item->id, 'qty' => 2, 'harga_satuan' => $item->harga_jual, 'satuan' => $item->satuan, 'nama_item' => $item->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => $total, 'kas_id' => $kas->id]],
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total,
        ], $this->cabang->id);

        $stok = fn () => (float) Stock::where('item_id', $item->id)->where('lokasi_id', $this->cabang->id)->value('qty');
        $this->assertEquals(8, $stok());
        $this->assertEquals(100000 + $total, (float) $kas->fresh()->saldo_sekarang);

        // Hapus langsung ditolak (Owner pun — kasir memang tidak punya izin hapus)
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->delete(route('penjualan.destroy', $order))->assertSessionHas('error');
        $this->assertNull($order->fresh()->deleted_at);

        // Batalkan dari daftar Riwayat (default: Manajer Cabang/Admin Pusat/Owner)
        $manajer = $this->buatUser("manajer_cabang", $this->cabang->id);
        $this->actingAs($manajer)->withSession(['active_cabang_id' => $this->cabang->id])
            ->from(route('penjualan.index'))
            ->post(route('penjualan.batalkan', $order), ['alasan_pembatalan_kategori' => 'Salah Input Kasir'])
            ->assertRedirect(route('penjualan.index'))
            ->assertSessionHas('success');

        $this->assertSame(StatusOrder::Dibatalkan, $order->fresh()->status);
        $this->assertEquals(10, $stok(), 'Stok harus kembali.');
        $this->assertEquals(100000, (float) $kas->fresh()->saldo_sekarang, 'Kas harus kembali.');

        // Setelah dibatalkan, Owner boleh menghapus
        $this->actingAs($owner)->delete(route('penjualan.destroy', $order))->assertSessionHas('success');
        $this->assertNotNull(Order::withTrashed()->find($order->id)->deleted_at);
    }

    // ------------------------------------------------------------------ riwayat

    public function test_riwayat_tombol_batalkan_dan_hapus_sesuai_status(): void
    {
        $selesai = $this->order(100000, 40000, 'selesai', now()->toDateString());
        $batal = $this->order(50000, 1, 'dibatalkan', now()->toDateString());
        $pending = $this->order(30000, 1, 'pending', now()->toDateString());
        $owner = $this->buatUser('owner');

        $html = $this->actingAs($owner)->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.index', ['search' => 'TST-OHB']))
            ->assertOk()->getContent();

        $this->assertStringContainsString(route('penjualan.batalkan', $selesai), $html, 'Order Selesai: tombol Batalkan ada.');
        $this->assertStringNotContainsString(route('penjualan.destroy', $selesai->id) . "'", $html, 'Order Selesai: tombol Hapus tidak ada.');
        $this->assertStringContainsString(route('penjualan.destroy', $batal->id) . "'", $html, 'Order Dibatalkan: tombol Hapus ada.');
        $this->assertStringContainsString(route('penjualan.destroy', $pending->id) . "'", $html, 'Bill Tersimpan: tombol Hapus ada.');
        $this->assertStringNotContainsString(route('penjualan.batalkan', $batal), $html, 'Order Dibatalkan: tidak ada tombol Batalkan.');
        $this->assertStringContainsString('modalBatalkanRiwayat', $html);
    }
}
