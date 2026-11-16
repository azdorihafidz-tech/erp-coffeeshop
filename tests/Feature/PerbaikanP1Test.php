<?php

namespace Tests\Feature;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\JenisOlahan;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Pelanggan;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\Stock;
use App\Models\TransaksiKeuangan;
use App\Services\CascadeDeleteService;
use App\Services\LaporanLabaRugiService;
use App\Services\PenjualanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap P porting dimsum 4.50 — bug 1–5:
 * 1 Edit order dibayar mengunci diskon/metode (kas & Setoran Kasir tidak rusak)
 * 2 Hapus pelanggan tidak menghapus order-nya + saklar Aktif
 * 3 Hapus barang: riwayat tetap, ditolak kalau masih dipakai, foto tidak dihapus
 * 4 Tambahan Gratis (Lacak Stok) & Produk Tambahan memotong stok di POS
 * 5 Jenis Menu di Produk Jual → Laba Rugi "Per Jenis Menu"
 */
class PerbaikanP1Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet P1 ' . uniqid(), 'kode_cabang' => 'P1' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true,
            'wajib_bukti_transfer' => false, 'wajib_bukti_qris' => false]);
        foreach (['tunai', 'transfer', 'qris'] as $jenis) {
            Kas::create(['cabang_id' => $this->c->id, 'default_untuk' => $jenis, 'nama_kas' => "Kas {$jenis} P1",
                'tipe_kas' => $jenis === 'tunai' ? 'tunai' : 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        }
    }

    private function kas(string $jenis): Kas
    {
        return Kas::where('cabang_id', $this->c->id)->where('default_untuk', $jenis)->firstOrFail();
    }

    private function produk(array $attr = []): Item
    {
        $item = Item::create(array_merge([
            'kode_item' => 'P1-' . strtoupper(substr(uniqid(), -8)), 'nama_item' => 'Produk P1',
            'tipe' => 'produk_jual', 'satuan' => 'pcs', 'harga_jual' => 10000, 'harga_beli_terakhir' => 0, 'is_active' => true, 'track_stok' => false,
        ], $attr));
        Stock::updateOrCreate(['item_id' => $item->id, 'lokasi_id' => $this->c->id], ['qty' => 0, 'qty_minimum' => 0]);

        return $item;
    }

    private function isiStok(Item $item, float $qty, float $harga = 1000): void
    {
        app(StokService::class)->masuk($item->id, $this->c->id, $qty, 'stok test P1', 'test', null, $harga);
    }

    private function jual(array $baris, array $payments, array $extra = []): Order
    {
        $kasir = $this->buatUser('kasir', $this->c->id);
        $this->actingAs($kasir);
        $items = array_map(fn ($b) => ['item_id' => $b[0]->id, 'qty' => $b[1], 'harga_satuan' => (float) $b[0]->harga_jual, 'satuan' => $b[0]->satuan, 'nama_item' => $b[0]->nama_item], $baris);

        return app(PenjualanService::class)->buatOrder(array_merge([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Pembeli P1', 'items' => $items, 'payments' => $payments,
            'tipe_pembayaran' => $payments[0]['metode'], 'jumlah_bayar' => array_sum(array_column($payments, 'jumlah')),
        ], $extra), $this->c->id);
    }

    // ---------------- 1. Edit order ----------------

    public function test_edit_order_dibayar_menolak_ubah_diskon_dan_metode_termasuk_owner(): void
    {
        $p = $this->produk();
        $this->isiStok($p, 10);
        $kas = $this->kas('tunai');
        $order = $this->jual([[$p, 1]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $kas->id]]);
        $saldo = (float) $kas->fresh()->saldo_sekarang;
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->put(route('penjualan.update', $order), ['nama_pelanggan' => 'X', 'tipe_pembayaran' => 'tunai', 'diskon' => '2000'])
            ->assertSessionHas('error');
        $this->assertEquals(10000, (float) $order->fresh()->total_bayar);
        $this->actingAs($owner)->put(route('penjualan.update', $order), ['nama_pelanggan' => 'X', 'tipe_pembayaran' => 'qris', 'diskon' => '0'])
            ->assertSessionHas('error');

        // nama & catatan tetap boleh — keterangan transaksi ikut, jumlah & saldo tidak berubah
        $this->actingAs($owner)->put(route('penjualan.update', $order), ['nama_pelanggan' => 'Nama Baru', 'catatan' => 'ok'])
            ->assertRedirect(route('penjualan.show', $order));
        $this->assertSame('Nama Baru', $order->fresh()->nama_pelanggan);
        $tk = TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $order->id)->first();
        $this->assertEquals(10000, (float) $tk->jumlah);
        $this->assertStringContainsString('Nama Baru', $tk->keterangan);
        $this->assertEquals($saldo, (float) $kas->fresh()->saldo_sekarang);

        $this->actingAs($owner)->get(route('penjualan.edit', $order))->assertOk()->assertSee('Diskon dan metode bayar dikunci');
    }

    public function test_ganti_nama_order_split_tidak_menimpa_jumlah_tiap_pembayaran(): void
    {
        $p = $this->produk();
        $this->isiStok($p, 10);
        $order = $this->jual([[$p, 1]], [
            ['metode' => 'transfer', 'jumlah' => 4000, 'kas_id' => $this->kas('transfer')->id],
            ['metode' => 'tunai', 'jumlah' => 6000, 'kas_id' => $this->kas('tunai')->id],
        ]);

        $this->actingAs($this->buatUser('owner'))->put(route('penjualan.update', $order), ['nama_pelanggan' => 'Ganti'])->assertRedirect();

        $jumlah = TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $order->id)->orderBy('jumlah')->pluck('jumlah')->map(fn ($j) => (float) $j)->all();
        $this->assertSame([4000.0, 6000.0], $jumlah);
        $this->assertEquals(10000, (float) OrderPayment::where('order_id', $order->id)->sum('jumlah'));
        $this->assertTrue(TransaksiKeuangan::where('referensi_id', $order->id)->where('referensi_type', 'order')->where('keterangan', 'like', 'Order % — Ganti (Tunai)')->exists());
    }

    public function test_edit_bill_pending_masih_bisa_ubah_diskon_dan_service_charge_ikut(): void
    {
        $order = Order::create([
            'nomor_order' => 'P1-PEND-' . uniqid(), 'cabang_id' => $this->c->id, 'tanggal_order' => today(),
            'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'status' => StatusOrder::Pending,
            'total_harga' => 20000, 'diskon' => 0, 'service_charge' => 2000, 'take_away_fee' => 1000, 'total_bayar' => 23000,
            'tipe_pembayaran' => 'tunai', 'kasir_id' => $this->buatUser('kasir', $this->c->id)->id,
        ]);
        $this->actingAs($this->buatUser('owner'))->put(route('penjualan.update', $order), ['tipe_pembayaran' => 'qris', 'diskon' => '10000'])->assertRedirect();
        $o = $order->fresh();
        $this->assertEquals(10000, (float) $o->diskon);
        $this->assertEquals(1000, (float) $o->service_charge);   // 10% dari 10.000
        $this->assertEquals(12000, (float) $o->total_bayar);     // 10.000 + 1.000 + 1.000
        $this->assertSame('qris', $o->tipe_pembayaran->value);
    }

    // ---------------- 2. Pelanggan ----------------

    public function test_hapus_pelanggan_tidak_menghapus_order_dan_restore_hanya_yang_ikut_terhapus(): void
    {
        $p = $this->produk();
        $this->isiStok($p, 10);
        $pel = Pelanggan::create(['kode_pelanggan' => 'PP1' . rand(1000, 9999), 'nama_pelanggan' => 'Pel P1', 'is_active' => true]);
        $order = $this->jual([[$p, 1]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $this->kas('tunai')->id]], ['pelanggan_id' => $pel->id]);

        $this->actingAs($this->buatUser('owner'))->delete(route('pelanggan.destroy', $pel))->assertSessionHas('success');
        $this->assertSoftDeleted('pelanggans', ['id' => $pel->id]);
        $this->assertNull($order->fresh()->deleted_at);
        $this->assertSame('Pel P1', $order->fresh()->pelanggan?->nama_pelanggan);

        // order lain yang dihapus sendiri TIDAK ikut kembali saat pelanggan dipulihkan
        $lain = Order::create([
            'nomor_order' => 'P1-L-' . uniqid(), 'cabang_id' => $this->c->id, 'tanggal_order' => today(), 'tipe_order' => 'penjualan',
            'tipe_transaksi' => 'takeaway', 'status' => StatusOrder::Dibatalkan, 'total_harga' => 0, 'diskon' => 0, 'total_bayar' => 0,
            'pelanggan_id' => $pel->id, 'kasir_id' => $order->kasir_id,
        ]);
        DB::table('orders')->where('id', $lain->id)->update(['deleted_at' => now()->subDay()]);
        app(CascadeDeleteService::class)->restorePelangganCascade($pel);
        $this->assertNotNull(DB::table('orders')->where('id', $lain->id)->value('deleted_at'));
        $this->assertNull(DB::table('pelanggans')->where('id', $pel->id)->value('deleted_at'));
    }

    public function test_pelanggan_bisa_dinonaktifkan_dan_hilang_dari_pos(): void
    {
        $pel = Pelanggan::create(['kode_pelanggan' => 'PN' . rand(10000, 99999), 'nama_pelanggan' => 'Pel Nonaktif P1', 'is_active' => true]);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->put(route('pelanggan.update', $pel), ['kode_pelanggan' => $pel->kode_pelanggan, 'nama_pelanggan' => $pel->nama_pelanggan])
            ->assertRedirect();
        $this->assertFalse((bool) $pel->fresh()->is_active);
        $this->assertFalse(Pelanggan::aktif()->whereKey($pel->id)->exists());
        $this->actingAs($owner)->get(route('pelanggan.index'))->assertOk()->assertSee('Nonaktif');

        $this->actingAs($owner)->put(route('pelanggan.update', $pel), ['kode_pelanggan' => $pel->kode_pelanggan, 'nama_pelanggan' => $pel->nama_pelanggan, 'is_active' => '1'])
            ->assertRedirect();
        $this->assertTrue((bool) $pel->fresh()->is_active);
        $this->actingAs($owner)->get(route('pelanggan.edit', $pel))->assertOk()->assertSee('pelangganAktif', false);
    }

    // ---------------- 3. Hapus barang ----------------

    public function test_hapus_barang_ditolak_kalau_masih_dipakai_dan_riwayat_tetap(): void
    {
        $bahan = $this->produk(['tipe' => 'bahan_baku', 'track_stok' => true, 'nama_item' => 'Bahan P1']);
        $this->isiStok($bahan, 5);
        $alasan = app(CascadeDeleteService::class)->alasanItemTidakBolehDihapus($bahan);
        $this->assertStringContainsString('masih ada stok', $alasan[0]);

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->delete(route('master.bahan-baku.destroy', $bahan))->assertSessionHas('error');
        $this->assertNull($bahan->fresh()->deleted_at);

        // produk jual yang sudah terjual (stok habis): boleh dihapus, baris order TIDAK ikut terhapus
        $p = $this->produk();
        $this->isiStok($p, 1);
        $order = $this->jual([[$p, 1]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $this->kas('tunai')->id]]);
        app(CascadeDeleteService::class)->deleteItemCascade($p->fresh());
        $this->assertSoftDeleted('items', ['id' => $p->id]);
        $this->assertSame(0, DB::table('order_items')->where('order_id', $order->id)->whereNotNull('deleted_at')->count());
    }

    public function test_hapus_bahan_yang_dipakai_komposisi_ditolak(): void
    {
        $bahan = $this->produk(['tipe' => 'bahan_baku', 'nama_item' => 'Bahan Komposisi P1']);
        $p = $this->produk(['nama_item' => 'Produk Pakai Bahan P1']);
        $resep = ResepBumbu::create(['nama' => 'Resep P1', 'kode' => 'RP1-' . substr(uniqid(), -8), 'item_id' => $p->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $bahan->id, 'qty_per_unit' => 1, 'satuan' => 'pcs']);

        $this->expectExceptionMessage('dipakai di komposisi: Produk Pakai Bahan P1');
        app(CascadeDeleteService::class)->deleteItemCascade($bahan);
    }

    public function test_hapus_produk_jual_tidak_menghapus_file_foto(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('produk/fotoP1.jpg', 'x');
        $p = $this->produk(['foto' => 'produk/fotoP1.jpg']);
        $this->actingAs($this->buatUser('owner'))->delete(route('master.produk-jual.destroy', $p))->assertRedirect(route('master.produk-jual.index'));
        $this->assertSoftDeleted('items', ['id' => $p->id]);
        Storage::disk('public')->assertExists('produk/fotoP1.jpg');
    }

    // ---------------- 4. Tambahan memotong stok ----------------

    public function test_tambahan_gratis_lacak_stok_dipotong_dan_habis_kalau_kosong(): void
    {
        $sedotan = $this->produk(['tipe' => 'tambahan_gratis', 'harga_jual' => 0, 'track_stok' => true, 'nama_item' => 'Sedotan P1']);
        $this->assertFalse($sedotan->bisaDijualDiCabang($this->c->id));

        $this->isiStok($sedotan, 3, 200);
        $this->assertTrue($sedotan->fresh()->bisaDijualDiCabang($this->c->id));
        $p = $this->produk();
        $this->isiStok($p, 5);
        $this->jual([[$p, 1], [$sedotan, 2]], [['metode' => 'tunai', 'jumlah' => 10000, 'kas_id' => $this->kas('tunai')->id]]);
        $this->assertEquals(1, app(StokService::class)->getStok($sedotan->id, $this->c->id));

        $this->expectExceptionMessage('Sedotan P1');
        $this->jual([[$sedotan, 2]], [['metode' => 'tunai', 'jumlah' => 0, 'kas_id' => $this->kas('tunai')->id]]);
    }

    public function test_tambahan_gratis_tanpa_lacak_stok_tidak_dipotong_produk_tambahan_dipotong(): void
    {
        $gula = $this->produk(['tipe' => 'tambahan_gratis', 'harga_jual' => 0, 'track_stok' => false, 'nama_item' => 'Gula Sachet P1']);
        $this->assertTrue($gula->bisaDijualDiCabang($this->c->id));
        $this->assertFalse($gula->dipotongStokSendiri());

        $extra = $this->produk(['tipe' => 'produk_tambahan', 'harga_jual' => 4000, 'nama_item' => 'Extra Shot P1']);
        $this->assertTrue($extra->dipotongStokSendiri());
        $this->isiStok($extra, 2);
        $this->jual([[$extra, 1], [$gula, 3]], [['metode' => 'tunai', 'jumlah' => 4000, 'kas_id' => $this->kas('tunai')->id]]);
        $this->assertEquals(1, app(StokService::class)->getStok($extra->id, $this->c->id));
    }

    public function test_produk_tambahan_berkomposisi_memotong_bahannya(): void
    {
        // Kasus ICE go-live: Produk Tambahan + komposisi Es Batu (gram)
        $esBatu = $this->produk(['tipe' => 'bahan_baku', 'satuan' => 'gram', 'nama_item' => 'Es Batu P1', 'track_stok' => true]);
        $this->isiStok($esBatu, 1000, 1);
        $ice = $this->produk(['tipe' => 'produk_tambahan', 'harga_jual' => 4000, 'nama_item' => 'ICE P1']);
        $resep = ResepBumbu::create(['nama' => 'Resep ICE P1', 'kode' => 'RICE-' . substr(uniqid(), -8), 'item_id' => $ice->id, 'is_active' => true]);
        ResepBumbuItem::create(['resep_bumbu_id' => $resep->id, 'item_id' => $esBatu->id, 'qty_per_unit' => 100, 'satuan' => 'gram']);

        $this->assertTrue($ice->fresh()->bisaDijualDiCabang($this->c->id));
        $order = $this->jual([[$ice, 2]], [['metode' => 'tunai', 'jumlah' => 8000, 'kas_id' => $this->kas('tunai')->id]]);
        $this->assertEquals(800, app(StokService::class)->getStok($esBatu->id, $this->c->id));
        $this->assertEquals(200, (float) DB::table('order_items')->where('order_id', $order->id)->value('hpp'));
    }

    // ---------------- 5. Jenis Menu ----------------

    public function test_jenis_menu_tersimpan_di_order_dan_laba_rugi_per_jenis_terisi(): void
    {
        $jenis = JenisOlahan::create(['nama' => 'Jenis P1', 'slug' => 'jenis-p1-' . uniqid(), 'is_active' => true]);
        $p = $this->produk(['jenis_olahan_id' => $jenis->id]);
        $this->isiStok($p, 5);
        $order = $this->jual([[$p, 2]], [['metode' => 'tunai', 'jumlah' => 20000, 'kas_id' => $this->kas('tunai')->id]]);
        $this->assertSame('Jenis P1', DB::table('order_items')->where('order_id', $order->id)->value('jenis_olahan'));

        // order lama tanpa snapshot → memakai Jenis Menu produk sekarang
        DB::table('order_items')->where('order_id', $order->id)->update(['jenis_olahan' => null]);
        $rows = app(LaporanLabaRugiService::class)->getBreakdownPerJenisOlahan(today()->startOfDay(), today()->endOfDay(), $this->c->id);
        $this->assertTrue(collect($rows)->contains(fn ($r) => $r->jenis_olahan === 'Jenis P1' && (float) $r->total_omzet === 20000.0));
    }

    public function test_form_produk_jual_punya_pilihan_jenis_menu_dan_tersimpan(): void
    {
        $jenis = JenisOlahan::create(['nama' => 'Jenis Form P1', 'slug' => 'jenis-form-p1-' . uniqid(), 'is_active' => true]);
        $p = $this->produk();
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('master.produk-jual.edit', $p))->assertOk()->assertSee('name="jenis_olahan_id"', false)->assertSee('Jenis Form P1');

        $this->actingAs($owner)->put(route('master.produk-jual.update', $p), [
            'kode_item' => $p->kode_item, 'nama_item' => $p->nama_item, 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_jual' => 10000, 'is_active' => 1, 'jenis_olahan_id' => $jenis->id,
            'cabang_aktif' => [$this->c->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame($jenis->id, (int) $p->fresh()->jenis_olahan_id);
    }
}
