<?php

namespace Tests\Feature;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\ResepBumbu;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Kirim ke Cabang (FASE_PRODUKSI_GEROBAK_DESIGN.md Tahap 2): gudang menjual
 * Produk Jual ke cabang sendiri lewat POS, cabang menekan Terima/Tolak.
 * Contoh Owner: Cup Kopi Isi modal Rp3.000 dikirim ke Gerobak @Rp3.500.
 */
class KirimanCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $gerobak;
    private Cabang $gerobak2;
    private Item $cupIsi;   // bahan setengah jadi (stok)
    private Item $kirimCup; // Produk Jual "kirim ke cabang", komposisi Cup Kopi Isi 1
    private Item $pack;     // Produk Jual tanpa komposisi, punya stok sendiri (pola Roastery)
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $u = rand(10000, 99999);
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang KC {$u}", 'kode_cabang' => "GKC{$u}", 'tipe' => 'gudang_pusat', 'is_active' => true, 'jenis_pembeli_aktif' => true]);
        $this->gerobak = Cabang::create(['nama_cabang' => "Gerobak 1 KC {$u}", 'kode_cabang' => "GR1{$u}", 'tipe' => 'cabang', 'is_active' => true]);
        $this->gerobak2 = Cabang::create(['nama_cabang' => "Gerobak 2 KC {$u}", 'kode_cabang' => "GR2{$u}", 'tipe' => 'cabang', 'is_active' => true]);
        foreach ([$this->gudang, $this->gerobak, $this->gerobak2] as $c) {
            Kas::create(['cabang_id' => $c->id, 'nama_kas' => "Kas Tunai {$c->kode_cabang}", 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
                'saldo_awal' => 500000, 'saldo_sekarang' => 500000, 'is_active' => true]);
        }

        $this->cupIsi = Item::create(['kode_item' => "KC-CUP-{$u}", 'nama_item' => 'Cup Kopi Isi KC', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 3000, 'harga_jual' => 0, 'is_active' => true]);
        $this->kirimCup = Item::create(['kode_item' => "KC-KRM-{$u}", 'nama_item' => 'Cup Kopi Isi (kirim ke cabang) KC', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 0, 'harga_jual' => 3500, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => "Resep kirim {$u}", 'kode' => "RK{$u}", 'item_id' => $this->kirimCup->id, 'is_active' => true]);
        $resep->items()->create(['item_id' => $this->cupIsi->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 1]);
        $this->pack = Item::create(['kode_item' => "KC-PCK-{$u}", 'nama_item' => 'Pack Biji 250g KC', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 20000, 'harga_jual' => 30000, 'is_active' => true]);

        $this->owner = $this->buatUser('owner');
        $this->actingAs($this->owner);
        app(StokService::class)->masuk($this->cupIsi->id, $this->gudang->id, 100, 'stok awal', 'uji', null, 3000);
        app(StokService::class)->masuk($this->pack->id, $this->gudang->id, 10, 'stok awal', 'uji', null, 20000);
    }

    private function stok(Item $i, Cabang $c): float
    {
        return (float) Stock::where('item_id', $i->id)->where('lokasi_id', $c->id)->value('qty');
    }

    private function kas(Cabang $c): float
    {
        return (float) Kas::where('cabang_id', $c->id)->where('default_untuk', 'tunai')->value('saldo_sekarang');
    }

    /** Kirim lewat POS gudang (HTTP, sama dengan JS POS). */
    private function kirim(array $items, array $ubah = [], ?User $user = null, ?Cabang $dari = null)
    {
        $total = collect($items)->sum(fn ($i) => $i[1] * $i[2]);

        return $this->actingAs($user ?? $this->owner)->withSession(['active_cabang_id' => ($dari ?? $this->gudang)->id])
            ->postJson(route('penjualan.store'), array_merge([
                'tipe_transaksi' => 'takeaway', 'customer_type' => 'internal', 'outlet_tujuan_id' => $this->gerobak->id,
                'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => $total, 'diskon' => 0,
                'payments' => [['metode' => 'tunai', 'jumlah' => max($total, 1)]],
                'items' => collect($items)->map(fn ($i) => ['item_id' => $i[0]->id, 'nama_item' => $i[0]->nama_item, 'qty' => $i[1], 'harga_satuan' => $i[2], 'satuan' => 'pcs'])->all(),
            ], $ubah));
    }

    private function kirimCup(int $qty = 50, array $ubah = []): Order
    {
        $this->kirim([[$this->kirimCup, $qty, 3500]], $ubah)->assertOk()->assertJsonPath('success', true);

        return Order::where('cabang_id', $this->gudang->id)->latest('id')->firstOrFail();
    }

    public function test_kirim_memotong_stok_gudang_tanpa_uang_dan_tidak_dihitung_laporan(): void
    {
        $o = $this->kirimCup();

        $this->assertSame(StatusOrder::Proses, $o->status);
        $this->assertSame('menunggu', $o->status_kirim);
        $this->assertSame((int) $this->gerobak->id, (int) $o->outlet_tujuan_id);
        $this->assertSame(0.0, (float) $o->service_charge);
        $this->assertNull($o->nomor_antrian);
        $this->assertEqualsWithDelta(50, $this->stok($this->cupIsi, $this->gudang), 0.001);
        $this->assertEqualsWithDelta(0, $this->stok($this->cupIsi, $this->gerobak), 0.001);
        $this->assertSame(500000.0, $this->kas($this->gudang));
        $this->assertSame(500000.0, $this->kas($this->gerobak));
        $this->assertSame(0, TransaksiKeuangan::where('referensi_id', $o->id)->whereIn('referensi_type', ['order', 'order_internal'])->count());

        $k = $o->kirimanItems()->firstOrFail();
        $this->assertSame((int) $this->cupIsi->id, (int) $k->item_id, 'yang dikirim = bahan komposisi');
        $this->assertEqualsWithDelta(50, (float) $k->qty_kirim, 0.001);
        $this->assertEqualsWithDelta(150000, (float) $k->hpp_kirim, 0.01);

        // Tidak dihitung di laporan mana pun selama belum diterima
        $this->assertSame(0, Order::terhitung()->where('id', $o->id)->count());
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('penjualan.laporan.transaksi', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]))
            ->assertOk()->assertViewHas('total', fn ($t) => (int) $t['transaksi'] === 0);
    }

    public function test_terima_penuh_memindahkan_stok_uang_dan_pendapatan(): void
    {
        $o = $this->kirimCup();
        $kasir = $this->buatUser('kasir', $this->gerobak->id);

        $this->actingAs($kasir)->get(route('kiriman.show', $o))->assertOk()->assertSee('Jumlah sampai');
        $this->actingAs($kasir)->post(route('kiriman.terima', $o), ['qty_diterima' => []])->assertRedirect(route('kiriman.show', $o))->assertSessionHas('success');

        $o->refresh();
        $this->assertSame(StatusOrder::Selesai, $o->status);
        $this->assertSame('diterima', $o->status_kirim);
        $this->assertSame((int) $kasir->id, (int) $o->diterima_by);
        $this->assertEqualsWithDelta(175000, (float) $o->total_bayar, 0.01);
        $this->assertSame(today()->toDateString(), $o->tanggal_order->toDateString());

        $this->assertEqualsWithDelta(50, $this->stok($this->cupIsi, $this->gerobak), 0.001);
        $this->assertEqualsWithDelta(3500, (float) StockBatch::where('item_id', $this->cupIsi->id)->where('lokasi_id', $this->gerobak->id)->value('harga_beli_per_unit'), 0.01, 'modal di gerobak = harga kiriman');
        $this->assertSame(500000.0 - 175000, $this->kas($this->gerobak));
        $this->assertSame(500000.0 + 175000, $this->kas($this->gudang));
        $this->assertSame(1, TransaksiKeuangan::where('referensi_type', 'order')->where('referensi_id', $o->id)->where('cabang_id', $this->gudang->id)->count());
        $this->assertSame(1, TransaksiKeuangan::where('referensi_type', 'order_internal')->where('referensi_id', $o->id)->where('cabang_id', $this->gerobak->id)->count());

        // Dihitung sebagai penjualan gudang (HPP 150.000 → untung 25.000), tidak dobel di Semua Cabang
        $this->assertSame(1, Order::terhitung()->where('id', $o->id)->count());
        $this->assertEqualsWithDelta(150000, (float) $o->items->sum('hpp'), 0.01);
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => null])
            ->get(route('penjualan.laporan.transaksi', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]))
            ->assertOk()->assertViewHas('internal', fn ($i) => $i && $i['transaksi'] >= 1);

        // Sudah diterima: tidak bisa diterima lagi, tidak bisa dibatalkan (pakai Retur)
        $this->actingAs($kasir)->post(route('kiriman.terima', $o))->assertSessionHas('error');
        $this->actingAs($this->owner)->post(route('penjualan.batalkan', $o), ['alasan_pembatalan_kategori' => 'Lainnya'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Retur'));
        $this->assertSame(StatusOrder::Selesai, $o->fresh()->status);
    }

    public function test_terima_sebagian_sisa_kembali_ke_gudang_dan_total_turun(): void
    {
        $o = $this->kirimCup(50);
        $oi = $o->items()->first();

        $this->actingAs($this->buatUser('kasir', $this->gerobak->id))
            ->post(route('kiriman.terima', $o), ['qty_diterima' => [$oi->id => 48]])->assertSessionHas('success');

        $o->refresh();
        $this->assertEqualsWithDelta(48, $this->stok($this->cupIsi, $this->gerobak), 0.001);
        $this->assertEqualsWithDelta(52, $this->stok($this->cupIsi, $this->gudang), 0.001, '2 cup kembali ke gudang');
        $this->assertEqualsWithDelta(168000, (float) $o->total_bayar, 0.01);
        $this->assertEqualsWithDelta(48, (float) $oi->fresh()->qty, 0.001);
        $this->assertEqualsWithDelta(144000, (float) $oi->fresh()->hpp, 0.01);
        $this->assertSame(500000.0 - 168000, $this->kas($this->gerobak));
        $this->assertSame(500000.0 + 168000, $this->kas($this->gudang));
        $k = $o->kirimanItems()->first();
        $this->assertEqualsWithDelta(48, (float) $k->qty_terima, 0.001);
        $this->assertEqualsWithDelta(168000, (float) $k->nilai_terima, 0.01);

        // Validasi jumlah
        $o2 = $this->kirimCup(10);
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gerobak->id])
            ->post(route('kiriman.terima', $o2), ['qty_diterima' => [$o2->items()->first()->id => 11]])->assertSessionHas('error');
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gerobak->id])
            ->post(route('kiriman.terima', $o2), ['qty_diterima' => [$o2->items()->first()->id => 0]])->assertSessionHas('error', fn ($m) => str_contains($m, 'Tolak'));
        $this->assertSame('menunggu', $o2->fresh()->status_kirim);
    }

    public function test_tolak_dan_batal_pengirim_mengembalikan_stok_tanpa_uang(): void
    {
        $o = $this->kirimCup(50);
        $kasir = $this->buatUser('kasir', $this->gerobak->id);

        $this->actingAs($kasir)->post(route('kiriman.tolak', $o), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->actingAs($kasir)->post(route('kiriman.tolak', $o), ['alasan' => 'salah kirim'])->assertSessionHas('success');
        $o->refresh();
        $this->assertSame(StatusOrder::Dibatalkan, $o->status);
        $this->assertSame('ditolak', $o->status_kirim);
        $this->assertSame('salah kirim', $o->alasan_tolak);
        $this->assertEqualsWithDelta(100, $this->stok($this->cupIsi, $this->gudang), 0.001);
        $this->assertEqualsWithDelta(0, $this->stok($this->cupIsi, $this->gerobak), 0.001);
        $this->assertSame(500000.0, $this->kas($this->gudang));
        $this->assertSame(500000.0, $this->kas($this->gerobak));
        // Nilai FIFO gudang utuh (100 × 3.000)
        $nilai = StockBatch::where('item_id', $this->cupIsi->id)->where('lokasi_id', $this->gudang->id)->get()->sum(fn ($b) => (float) $b->qty_sisa * (float) $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta(300000, $nilai, 0.5);
        $this->actingAs($kasir)->post(route('kiriman.terima', $o))->assertSessionHas('error');
        $this->actingAs($this->owner)->post(route('penjualan.kembalikan', $o))->assertSessionHas('error', fn ($m) => str_contains($m, 'kiriman baru'));

        // Pengirim membatalkan sebelum diterima
        $o2 = $this->kirimCup(20);
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])
            ->post(route('kiriman.batalkan', $o2), ['alasan' => 'jadwal berubah'])->assertSessionHas('success');
        $this->assertSame('dibatalkan', $o2->fresh()->status_kirim);
        $this->assertEqualsWithDelta(100, $this->stok($this->cupIsi, $this->gudang), 0.001);

        // Batalkan dari Detail Order (penjualan.batalkan) juga mengembalikan stok
        $o3 = $this->kirimCup(5);
        $this->actingAs($this->owner)->post(route('penjualan.batalkan', $o3), ['alasan_pembatalan_kategori' => 'Lainnya'])->assertSessionHas('success');
        $this->assertSame('dibatalkan', $o3->fresh()->status_kirim);
        $this->assertEqualsWithDelta(100, $this->stok($this->cupIsi, $this->gudang), 0.001);
    }

    public function test_hak_akses_dan_aturan_kirim(): void
    {
        $o = $this->kirimCup(10);

        // Kasir cabang lain / kasir gudang tidak bisa menerima
        $this->actingAs($this->buatUser('kasir', $this->gerobak2->id))->post(route('kiriman.terima', $o))->assertForbidden();
        $this->actingAs($this->buatUser('kasir', $this->gerobak2->id))->get(route('kiriman.show', $o))->assertForbidden();
        $this->actingAs($this->buatUser('kasir', $this->gudang->id))->post(route('kiriman.terima', $o))->assertForbidden();
        // Helper tanpa hak
        $this->actingAs($this->buatUser('helper', $this->gerobak->id))->get(route('kiriman.index'))->assertForbidden();
        // Gerobak lain tidak melihat di daftarnya
        $this->actingAs($this->buatUser('kasir', $this->gerobak2->id))->get(route('kiriman.index'))->assertOk()->assertDontSee($o->nomor_order);
        $this->actingAs($this->buatUser('kasir', $this->gerobak->id))->get(route('kiriman.index'))->assertOk()->assertSee($o->nomor_order);
        $this->assertSame('menunggu', $o->fresh()->status_kirim);

        // Aturan kirim
        $this->kirim([[$this->kirimCup, 1, 3500]], [], null, $this->gerobak2)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Gudang Pusat atau Roastery'));
        $this->kirim([[$this->kirimCup, 1, 3500]], ['outlet_tujuan_id' => $this->gudang->id])->assertStatus(422);
        Kas::where('cabang_id', $this->gerobak2->id)->update(['is_active' => false]);
        $this->kirim([[$this->kirimCup, 1, 3500]], ['outlet_tujuan_id' => $this->gerobak2->id])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Kas Tunai'));
        $this->kirim([[$this->kirimCup, 999, 3500]])->assertStatus(422);

        // Save Bill tidak boleh untuk kiriman
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.simpan-bill'), ['customer_type' => 'internal', 'tipe_transaksi' => 'takeaway', 'items' => [['item_id' => $this->kirimCup->id, 'nama_item' => 'x', 'qty' => 1, 'harga_satuan' => 3500]]])
            ->assertStatus(422);
    }

    public function test_produk_tanpa_komposisi_dan_diskon_dikirim_sebagai_barang_itu_sendiri(): void
    {
        $this->kirim([[$this->pack, 3, 30000], [$this->kirimCup, 10, 3500]], ['diskon' => 12500])->assertOk();
        $o = Order::where('cabang_id', $this->gudang->id)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(7, $this->stok($this->pack, $this->gudang), 0.001);
        $this->assertEqualsWithDelta(112500, (float) $o->total_bayar, 0.01, '90.000 + 35.000 − diskon 12.500');

        $this->actingAs($this->buatUser('manajer_cabang', $this->gerobak->id))->post(route('kiriman.terima', $o))->assertSessionHas('success');
        $o->refresh();
        $this->assertEqualsWithDelta(3, $this->stok($this->pack, $this->gerobak), 0.001);
        $this->assertEqualsWithDelta(10, $this->stok($this->cupIsi, $this->gerobak), 0.001);
        // Total nilai stok masuk di gerobak = yang dibayar gerobak (diskon ikut dibagi)
        $nilai = StockBatch::whereIn('item_id', [$this->pack->id, $this->cupIsi->id])->where('lokasi_id', $this->gerobak->id)
            ->get()->sum(fn ($b) => (float) $b->qty_awal * (float) $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta((float) $o->total_bayar, $nilai, 1.0);
        $this->assertEqualsWithDelta(112500, (float) $o->total_bayar, 0.01);
        $this->assertSame(500000.0 - 112500, $this->kas($this->gerobak));
    }

    public function test_tampilan_pos_menu_dan_halaman(): void
    {
        // POS gudang: ada pilihan Kirim ke Cabang; POS cabang biasa: tidak ada
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.pos'))
            ->assertOk()->assertSee('Kirim ke Cabang (menunggu diterima)')->assertSee($this->gerobak->nama_cabang)->assertSee('infoKirimCabang', false);
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gerobak->id])->get(route('penjualan.pos'))
            ->assertOk()->assertDontSee('Kirim ke Cabang (menunggu diterima)');

        $o = $this->kirimCup(4);
        $kasir = $this->buatUser('kasir', $this->gerobak->id);
        $this->actingAs($kasir)->get(route('kiriman.index'))->assertOk()->assertSee('Cek &amp; Terima', false)->assertSee('<span>Kiriman Cabang</span>', false);
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('kiriman.index', ['arah' => 'keluar']))->assertOk()->assertSee($o->nomor_order);
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('kiriman.show', $o))->assertOk()->assertSee('Batalkan Kiriman');
        $this->actingAs($this->owner)->get(route('penjualan.show', $o))->assertOk()->assertSee('Dikirim (menunggu diterima)');
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.index'))->assertOk();
    }
}
