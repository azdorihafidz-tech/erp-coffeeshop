<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\ResepBumbu;
use App\Models\ReturInternal;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Retur Cabang (FASE_PRODUKSI_GEROBAK_DESIGN.md Tahap 3): gerobak mengembalikan
 * Cup Kopi Isi basi ke gudang, uang kembali dipilih per retur.
 * Kiriman: 50 cup modal 3.000 → dijual 3.500 (pendapatan gudang 175.000, untung 25.000).
 */
class ReturCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $gerobak;
    private Cabang $gerobak2;
    private Item $cupIsi;
    private Item $kirimCup;
    private User $owner;
    private Order $kiriman;

    protected function setUp(): void
    {
        parent::setUp();
        $u = rand(10000, 99999);
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang RC {$u}", 'kode_cabang' => "GRC{$u}", 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->gerobak = Cabang::create(['nama_cabang' => "Gerobak RC {$u}", 'kode_cabang' => "RC1{$u}", 'tipe' => 'cabang', 'is_active' => true]);
        $this->gerobak2 = Cabang::create(['nama_cabang' => "Gerobak2 RC {$u}", 'kode_cabang' => "RC2{$u}", 'tipe' => 'cabang', 'is_active' => true]);
        foreach ([$this->gudang, $this->gerobak, $this->gerobak2] as $c) {
            Kas::create(['cabang_id' => $c->id, 'nama_kas' => "Kas Tunai {$c->kode_cabang}", 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
                'saldo_awal' => 500000, 'saldo_sekarang' => 500000, 'is_active' => true]);
        }
        $this->cupIsi = Item::create(['kode_item' => "RC-CUP-{$u}", 'nama_item' => 'Cup Kopi Isi RC', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 3000, 'harga_jual' => 0, 'is_active' => true]);
        $this->kirimCup = Item::create(['kode_item' => "RC-KRM-{$u}", 'nama_item' => 'Cup Kopi Isi (kirim) RC', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 0, 'harga_jual' => 3500, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => "Resep RC {$u}", 'kode' => "RRC{$u}", 'item_id' => $this->kirimCup->id, 'is_active' => true]);
        $resep->items()->create(['item_id' => $this->cupIsi->id, 'qty_per_unit' => 1, 'satuan' => 'pcs', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 1]);

        $this->owner = $this->buatUser('owner');
        $this->actingAs($this->owner);
        app(StokService::class)->masuk($this->cupIsi->id, $this->gudang->id, 100, 'stok awal', 'uji', null, 3000);

        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->postJson(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'customer_type' => 'internal', 'outlet_tujuan_id' => $this->gerobak->id,
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 175000, 'payments' => [['metode' => 'tunai', 'jumlah' => 175000]],
            'items' => [['item_id' => $this->kirimCup->id, 'nama_item' => $this->kirimCup->nama_item, 'qty' => 50, 'harga_satuan' => 3500, 'satuan' => 'pcs']],
        ])->assertOk();
        $this->kiriman = Order::where('cabang_id', $this->gudang->id)->latest('id')->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $this->gerobak->id))->post(route('kiriman.terima', $this->kiriman))->assertSessionHas('success');
        $this->kiriman->refresh();
    }

    private function stok(Cabang $c): float
    {
        return (float) Stock::where('item_id', $this->cupIsi->id)->where('lokasi_id', $c->id)->value('qty');
    }

    private function kas(Cabang $c): float
    {
        return (float) Kas::where('cabang_id', $c->id)->where('default_untuk', 'tunai')->value('saldo_sekarang');
    }

    private function retur(float $qty, bool $uang, ?User $user = null)
    {
        $row = $this->kiriman->kirimanItems()->first();

        return $this->actingAs($user ?? $this->buatUser('kasir', $this->gerobak->id))
            ->post(route('retur-cabang.store', $this->kiriman), ['qty' => [$row->id => $qty], 'alasan' => 'basi', 'uang_kembali' => $uang ? 1 : 0, 'catatan' => 'sore basi']);
    }

    private function pendapatanGudangHariIni(): array
    {
        $res = $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])
            ->get(route('penjualan.laporan.transaksi', ['dari' => today()->toDateString(), 'sampai' => today()->toDateString()]));

        return $res->viewData('total');
    }

    public function test_retur_uang_kembali_mengurangi_pendapatan_dan_memindahkan_uang(): void
    {
        $this->assertSame(175000.0, (float) $this->pendapatanGudangHariIni()['pendapatan']);

        $this->retur(5, true)->assertSessionHas('success');
        $retur = ReturInternal::latest('id')->firstOrFail();
        $this->assertSame('menunggu', $retur->status);
        $this->assertEqualsWithDelta(17500, (float) $retur->nilai, 0.01);
        $this->assertEqualsWithDelta(45, $this->stok($this->gerobak), 0.001, 'stok gerobak langsung keluar');
        $this->assertEqualsWithDelta(50, $this->stok($this->gudang), 0.001, 'belum masuk gudang');
        $this->assertSame(500000.0 - 175000, $this->kas($this->gerobak), 'uang belum pindah');

        $this->actingAs($this->buatUser('admin_gudang', $this->gudang->id))->post(route('retur-cabang.terima', $retur))->assertSessionHas('success');
        $retur->refresh();
        $this->assertSame('diterima', $retur->status);
        $this->assertEqualsWithDelta(55, $this->stok($this->gudang), 0.001);
        $this->assertSame(500000.0 - 175000 + 17500, $this->kas($this->gerobak));
        $this->assertSame(500000.0 + 175000 - 17500, $this->kas($this->gudang));
        $this->assertEqualsWithDelta(3000, (float) StockBatch::where('item_id', $this->cupIsi->id)->where('lokasi_id', $this->gudang->id)
            ->where('referensi_type', 'retur_internal')->value('harga_beli_per_unit'), 0.01, 'kembali dengan modal asli gudang');

        // Order koreksi minus: pendapatan 175.000 − 17.500, untung 25.000 − 2.500
        $koreksi = $retur->orderKoreksi;
        $this->assertNotNull($koreksi);
        $this->assertEqualsWithDelta(-17500, (float) $koreksi->total_bayar, 0.01);
        $this->assertEqualsWithDelta(-5, (float) $koreksi->items->first()->qty, 0.001);
        $this->assertEqualsWithDelta(-15000, (float) $koreksi->items->first()->hpp, 0.01);
        $this->assertSame('internal', $koreksi->customer_type);
        $tot = $this->pendapatanGudangHariIni();
        $this->assertEqualsWithDelta(157500, (float) $tot['pendapatan'], 0.01);
        $this->assertEqualsWithDelta(22500, (float) $tot['keuntungan'], 0.01);

        // Transaksi keuangan = cermin minus; saldo kas hasil hitung transaksi = saldo kas
        $penjualanGudang = (float) TransaksiKeuangan::where('cabang_id', $this->gudang->id)->where('kategori', 'penjualan')->sum('jumlah');
        $this->assertEqualsWithDelta(157500, $penjualanGudang, 0.01);
        foreach ([$this->gudang, $this->gerobak] as $c) {
            $kas = Kas::where('cabang_id', $c->id)->where('default_untuk', 'tunai')->first();
            $net = (float) TransaksiKeuangan::where('kas_id', $kas->id)->selectRaw("SUM(CASE WHEN tipe = 'pemasukan' THEN jumlah ELSE -jumlah END) n")->value('n');
            $this->assertEqualsWithDelta((float) $kas->saldo_sekarang, 500000 + $net, 0.01, "kas {$c->nama_cabang} konsisten");
        }

        // Order koreksi dikunci
        $this->actingAs($this->owner)->post(route('penjualan.batalkan', $koreksi), ['alasan_pembatalan_kategori' => 'Lainnya'])->assertSessionHas('error');
        $this->assertFalse($koreksi->fresh()->bolehDihapus());
        $this->actingAs($this->owner)->get(route('penjualan.edit', $koreksi))->assertRedirect(route('penjualan.show', $koreksi));
        $this->actingAs($this->owner)->post(route('retur-cabang.terima', $retur))->assertSessionHas('error');
    }

    public function test_retur_tanpa_uang_kembali_rugi_di_cabang(): void
    {
        $this->retur(4, false)->assertSessionHas('success');
        $retur = ReturInternal::latest('id')->firstOrFail();
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->post(route('retur-cabang.terima', $retur))->assertSessionHas('success');

        $this->assertNull($retur->fresh()->orderKoreksi);
        $this->assertEqualsWithDelta(54, $this->stok($this->gudang), 0.001);
        $this->assertSame(500000.0 - 175000, $this->kas($this->gerobak));
        $this->assertSame(500000.0 + 175000, $this->kas($this->gudang));
        $this->assertSame(175000.0, (float) $this->pendapatanGudangHariIni()['pendapatan']);
        $batch = StockBatch::where('item_id', $this->cupIsi->id)->where('lokasi_id', $this->gudang->id)->where('referensi_type', 'retur_internal')->firstOrFail();
        $this->assertSame(0.0, (float) $batch->harga_beli_per_unit, 'nilai stok retur Rp0');
        $this->assertEqualsWithDelta(4, (float) $batch->qty_sisa, 0.001);
        // Nilai stok gudang tidak bertambah dari retur tanpa uang (50 sisa × 3.000)
        $nilai = StockBatch::where('item_id', $this->cupIsi->id)->where('lokasi_id', $this->gudang->id)->get()->sum(fn ($b) => (float) $b->qty_sisa * (float) $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta(150000, $nilai, 0.5);
    }

    public function test_batas_jumlah_tolak_dan_batal_mengembalikan_stok_cabang(): void
    {
        $this->retur(51, true)->assertSessionHas('error', fn ($m) => str_contains($m, 'maksimal'));
        $this->retur(0, true)->assertSessionHas('error');

        $this->retur(30, true)->assertSessionHas('success');
        $r1 = ReturInternal::latest('id')->firstOrFail();
        $this->retur(21, true)->assertSessionHas('error', fn ($m) => str_contains($m, 'maksimal'));

        // Gudang menolak → stok kembali ke gerobak, jatah retur pulih
        $this->actingAs($this->owner)->post(route('retur-cabang.tolak', $r1), ['alasan' => 'masih layak jual'])->assertSessionHas('success');
        $this->assertSame('ditolak', $r1->fresh()->status);
        $this->assertEqualsWithDelta(50, $this->stok($this->gerobak), 0.001);
        $this->assertEqualsWithDelta(0, (float) $this->kiriman->kirimanItems()->first()->qty_retur, 0.001);
        $nilai = StockBatch::where('item_id', $this->cupIsi->id)->where('lokasi_id', $this->gerobak->id)->get()->sum(fn ($b) => (float) $b->qty_sisa * (float) $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta(175000, $nilai, 0.5, 'nilai stok gerobak utuh');

        // Cabang membatalkan sendiri
        $this->retur(10, false)->assertSessionHas('success');
        $r2 = ReturInternal::latest('id')->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $this->gerobak->id))->post(route('retur-cabang.batalkan', $r2), ['alasan' => 'salah pilih'])->assertSessionHas('success');
        $this->assertSame('dibatalkan', $r2->fresh()->status);
        $this->assertEqualsWithDelta(50, $this->stok($this->gerobak), 0.001);
        $this->assertSame(500000.0 + 175000, $this->kas($this->gudang));
    }

    public function test_hak_akses_retur(): void
    {
        // Cabang lain tidak bisa membuat retur dari kiriman ini
        $this->retur(1, true, $this->buatUser('kasir', $this->gerobak2->id))->assertForbidden();
        $this->retur(2, true)->assertSessionHas('success');
        $retur = ReturInternal::latest('id')->firstOrFail();

        // Kasir gerobak tidak punya hak terima retur; admin gudang lain tidak boleh
        $this->actingAs($this->buatUser('kasir', $this->gerobak->id))->post(route('retur-cabang.terima', $retur))->assertForbidden();
        $lain = Cabang::create(['nama_cabang' => 'Gudang Lain RC', 'kode_cabang' => 'GLRC' . rand(100, 999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->actingAs($this->buatUser('admin_gudang', $lain->id))->post(route('retur-cabang.terima', $retur))->assertForbidden();
        $this->actingAs($this->buatUser('admin_gudang', $lain->id))->get(route('retur-cabang.show', $retur))->assertForbidden();
        $this->actingAs($this->buatUser('helper', $this->gerobak->id))->get(route('retur-cabang.index'))->assertForbidden();
        // Gudang tidak boleh membatalkan retur milik cabang (hanya tolak)
        $this->actingAs($this->buatUser('admin_gudang', $this->gudang->id))->post(route('retur-cabang.batalkan', $retur), ['alasan' => 'x x x'])->assertForbidden();
        $this->assertSame('menunggu', $retur->fresh()->status);
    }

    public function test_halaman_dan_menu(): void
    {
        $kasir = $this->buatUser('kasir', $this->gerobak->id);
        $this->actingAs($kasir)->get(route('kiriman.show', $this->kiriman))->assertOk()->assertSee('Buat Retur');
        $this->actingAs($kasir)->get(route('retur-cabang.create', $this->kiriman))->assertOk()->assertSee('Uang dikembalikan?');
        $this->retur(3, true, $kasir);
        $retur = ReturInternal::latest('id')->firstOrFail();
        $this->actingAs($kasir)->get(route('retur-cabang.index'))->assertOk()->assertSee($retur->kode_retur)->assertSee('<span>Retur Cabang</span>', false);
        $this->actingAs($kasir)->get(route('retur-cabang.show', $retur))->assertOk()->assertSee('Batalkan Retur');
        $ag = $this->buatUser('admin_gudang', $this->gudang->id);
        $this->actingAs($ag)->get(route('retur-cabang.index', ['arah' => 'masuk']))->assertOk()->assertSee('Cek &amp; Terima', false);
        $this->actingAs($ag)->get(route('retur-cabang.show', $retur))->assertOk()->assertSee('Terima Retur');
        $this->actingAs($ag)->post(route('retur-cabang.terima', $retur));
        $this->actingAs($this->owner)->get(route('retur-cabang.show', $retur))->assertOk()->assertSee($retur->fresh()->orderKoreksi->nomor_order);
        $this->actingAs($this->owner)->get(route('penjualan.show', $retur->fresh()->orderKoreksi))->assertOk();
        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.index'))->assertOk();
    }
}
