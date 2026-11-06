<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\ProduksiBatch;
use App\Models\ResepBumbu;
use App\Models\ResepProduksi;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\PenjualanService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Produksi bahan setengah jadi di gudang (FASE_PRODUKSI_GEROBAK_DESIGN.md Tahap 1).
 * Contoh Owner: Saus Padang dari tepung (stok kg, takaran g), cabai (stok kg),
 * saus tomat (stok liter, takaran ml), lalu dipakai di komposisi Sate Ayam.
 */
class ProduksiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $outlet;
    private Item $tepung;
    private Item $cabai;
    private Item $tomat;
    private Item $saus;

    protected function setUp(): void
    {
        parent::setUp();
        $u = uniqid();
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang Uji {$u}", 'kode_cabang' => 'GU' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->outlet = Cabang::create(['nama_cabang' => "Outlet Uji {$u}", 'kode_cabang' => 'OU' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);

        $this->tepung = $this->barang('Tepung Uji', 'kg', 10000);
        $this->cabai = $this->barang('Cabai Uji', 'kg', 40000);
        $this->tomat = $this->barang('Saus Tomat Uji', 'liter', 20000);
        $this->saus = $this->barang('Saus Padang Uji', 'liter', 0);

        $stok = app(StokService::class);
        $this->actingAs($this->buatUser('owner'));
        $stok->masuk($this->tepung->id, $this->gudang->id, 2, 'uji', 'uji', null, 10000);
        $stok->masuk($this->cabai->id, $this->gudang->id, 3, 'uji', 'uji', null, 40000);
        $stok->masuk($this->tomat->id, $this->gudang->id, 5, 'uji', 'uji', null, 20000);
    }

    private function barang(string $nama, string $satuan, float $harga): Item
    {
        return Item::create([
            'kode_item' => 'PRDUJI-' . strtoupper(substr(md5($nama . uniqid()), 0, 8)), 'nama_item' => $nama,
            'tipe' => 'bahan_baku', 'satuan' => $satuan, 'harga_beli_terakhir' => $harga, 'harga_jual' => 0, 'is_active' => true,
        ]);
    }

    private function stok(Item $i, ?Cabang $c = null): float
    {
        return (float) Stock::where('item_id', $i->id)->where('lokasi_id', ($c ?? $this->gudang)->id)->value('qty');
    }

    /** Resep: 1 liter Saus Padang = tepung 100 g + cabai 200 g + saus tomat 300 ml. */
    private function resep(): ResepProduksi
    {
        $this->actingAs($this->buatUser('owner'))->post(route('produksi.resep.store'), [
            'item_id' => $this->saus->id, 'qty_hasil' => 1, 'is_active' => 1,
            'bahan' => [
                ['item_id' => $this->tepung->id, 'qty' => 100, 'satuan' => 'g'],
                ['item_id' => $this->cabai->id, 'qty' => 200, 'satuan' => 'g'],
                ['item_id' => $this->tomat->id, 'qty' => 300, 'satuan' => 'ml'],
                ['item_id' => '', 'qty' => '', 'satuan' => ''], // baris kosong diabaikan
            ],
        ])->assertRedirect(route('produksi.resep.index'))->assertSessionHas('success');

        return ResepProduksi::where('item_id', $this->saus->id)->firstOrFail();
    }

    private function produksi(array $ubah = [], ?\App\Models\User $user = null)
    {
        $resep = ResepProduksi::where('item_id', $this->saus->id)->first() ?? $this->resep();

        return $this->actingAs($user ?? $this->buatUser('owner'))->post(route('produksi.store'), array_merge([
            'cabang_id' => $this->gudang->id, 'resep_produksi_id' => $resep->id, 'tanggal' => today()->toDateString(),
            'qty_rencana' => 5, 'qty_hasil' => 4.8, 'catatan' => 'masak pagi',
        ], $ubah));
    }

    public function test_resep_tersimpan_dan_validasi(): void
    {
        $resep = $this->resep();
        $this->assertCount(3, $resep->items);
        $this->assertSame([$this->tepung->id => 0.1, $this->cabai->id => 0.2, $this->tomat->id => 0.3], $resep->fresh('items.bahan')->kebutuhan(1));

        $owner = $this->buatUser('owner');
        // Barang hasil dipakai sebagai bahan sendiri → ditolak
        $this->actingAs($owner)->post(route('produksi.resep.store'), [
            'item_id' => $this->cabai->id, 'qty_hasil' => 1, 'bahan' => [['item_id' => $this->cabai->id, 'qty' => 1, 'satuan' => 'kg']],
        ])->assertSessionHas('error');
        // Barang yang sudah punya resep → ditolak (satu barang satu resep)
        $this->actingAs($owner)->post(route('produksi.resep.store'), [
            'item_id' => $this->saus->id, 'qty_hasil' => 1, 'bahan' => [['item_id' => $this->tepung->id, 'qty' => 1, 'satuan' => 'kg']],
        ])->assertSessionHas('error');
        $this->assertSame(1, ResepProduksi::where('item_id', $this->saus->id)->count());

        // Ubah resep
        $this->actingAs($owner)->put(route('produksi.resep.update', $resep), [
            'item_id' => $this->saus->id, 'qty_hasil' => 2, 'is_active' => 1,
            'bahan' => [['item_id' => $this->tepung->id, 'qty' => 0.2, 'satuan' => 'kg']],
        ])->assertRedirect(route('produksi.resep.index'));
        $this->assertSame(2.0, (float) $resep->fresh()->qty_hasil);
        $this->assertCount(1, $resep->fresh()->items);
    }

    public function test_produksi_memotong_bahan_dan_menambah_stok_hasil_dengan_modal_fifo(): void
    {
        $res = $this->produksi();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        $res->assertRedirect(route('produksi.show', $batch));

        // Bahan = resep × 5: tepung 0,5 kg, cabai 1 kg, saus tomat 1,5 liter
        $this->assertEqualsWithDelta(1.5, $this->stok($this->tepung), 0.0001);
        $this->assertEqualsWithDelta(2.0, $this->stok($this->cabai), 0.0001);
        $this->assertEqualsWithDelta(3.5, $this->stok($this->tomat), 0.0001);
        $this->assertEqualsWithDelta(4.8, $this->stok($this->saus), 0.0001);

        // Biaya FIFO: 0,5×10.000 + 1×40.000 + 1,5×20.000 = 75.000 → ÷ 4,8 = 15.625 / liter
        $this->assertEqualsWithDelta(75000, (float) $batch->biaya_bahan, 0.01);
        $this->assertEqualsWithDelta(15625, (float) $batch->modal_otomatis, 0.01);
        $this->assertEqualsWithDelta(15625, (float) $batch->modal_per_unit, 0.01);
        $this->assertFalse($batch->modal_manual);
        $this->assertSame('PRD-' . now()->format('Ym') . '-', substr($batch->kode_batch, 0, 11));
        $this->assertCount(3, $batch->bahan);

        $fifo = StockBatch::where('item_id', $this->saus->id)->where('lokasi_id', $this->gudang->id)->firstOrFail();
        $this->assertEqualsWithDelta(15625, (float) $fifo->harga_beli_per_unit, 0.01);
        $this->assertSame('produksi', $fifo->referensi_type);
        $this->assertEqualsWithDelta(15625, (float) $this->saus->fresh()->harga_beli_terakhir, 0.01);
        $this->assertSame(4, StockMovement::where('referensi_type', 'produksi')->where('referensi_id', $batch->id)->count(), '3 bahan keluar + 1 hasil masuk');

        $this->actingAs($this->buatUser('owner'))->get(route('produksi.show', $batch))->assertOk()
            ->assertSee($batch->kode_batch)->assertSee('Saus Padang Uji')->assertSee('15.625');
    }

    public function test_modal_manual_dipakai_untuk_stok_hasil(): void
    {
        $this->produksi(['pakai_modal_manual' => 1, 'modal_manual' => 18000]);
        $batch = ProduksiBatch::latest('id')->firstOrFail();

        $this->assertTrue($batch->modal_manual);
        $this->assertEqualsWithDelta(15625, (float) $batch->modal_otomatis, 0.01, 'angka otomatis tetap tercatat');
        $this->assertEqualsWithDelta(18000, (float) $batch->modal_per_unit, 0.01);
        $this->assertEqualsWithDelta(18000, (float) StockBatch::where('item_id', $this->saus->id)->value('harga_beli_per_unit'), 0.01);

        // Isian manual tanpa saklar dinyalakan → diabaikan
        $this->produksi(['qty_rencana' => 1, 'qty_hasil' => 1, 'modal_manual' => 99999]);
        $this->assertFalse(ProduksiBatch::latest('id')->first()->modal_manual);
    }

    public function test_stok_bahan_kurang_ditolak_tanpa_mengubah_stok(): void
    {
        $this->produksi(['qty_rencana' => 20, 'qty_hasil' => 20]) // butuh tepung 2 kg (ada 2), cabai 4 kg (ada 3), tomat 6 l (ada 5)
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Cabai Uji') && str_contains($m, 'Saus Tomat Uji') && ! str_contains($m, 'Tepung Uji'));

        $this->assertSame(0, ProduksiBatch::where('item_id', $this->saus->id)->count());
        $this->assertEqualsWithDelta(2, $this->stok($this->tepung), 0.0001);
        $this->assertEqualsWithDelta(3, $this->stok($this->cabai), 0.0001);
        $this->assertEqualsWithDelta(0, $this->stok($this->saus), 0.0001);
    }

    public function test_hanya_di_gudang_dan_sesuai_hak_akses(): void
    {
        $this->resep();
        // Cabang bukan gudang → ditolak
        $this->produksi(['cabang_id' => $this->outlet->id])->assertSessionHasErrors('cabang_id');

        // Admin Gudang di gudang ini boleh; Admin Gudang gudang lain tidak bisa memilih gudang ini
        $this->produksi([], $this->buatUser('admin_gudang', $this->gudang->id))->assertSessionHasNoErrors()->assertSessionHas('success');
        $lain = Cabang::create(['nama_cabang' => 'Gudang Lain ' . uniqid(), 'kode_cabang' => 'GL' . rand(10000, 99999), 'tipe' => 'gudang_pusat', 'is_active' => true]);
        $this->produksi([], $this->buatUser('admin_gudang', $lain->id))->assertSessionHasErrors('cabang_id');
        $batch = ProduksiBatch::latest('id')->first();
        $this->actingAs($this->buatUser('admin_gudang', $lain->id))->get(route('produksi.show', $batch))->assertNotFound();

        // Kasir tidak punya hak menu Produksi
        $kasir = $this->buatUser('kasir', $this->outlet->id);
        $this->actingAs($kasir)->get(route('produksi.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('produksi.resep.create'))->assertForbidden();
        $this->produksi([], $kasir)->assertForbidden();

        // Operator Produksi: boleh catat, tidak boleh kelola resep / batalkan
        $op = $this->buatUser('operator_produksi', $this->gudang->id);
        $this->actingAs($op)->get(route('produksi.create'))->assertOk();
        $this->actingAs($op)->get(route('produksi.resep.create'))->assertForbidden();
        $this->actingAs($op)->post(route('produksi.batalkan', $batch), ['alasan' => 'coba'])->assertForbidden();
    }

    public function test_batalkan_mengembalikan_bahan_dan_menarik_hasil(): void
    {
        $this->produksi();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->post(route('produksi.batalkan', $batch), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->actingAs($owner)->post(route('produksi.batalkan', $batch), ['alasan' => 'salah input jumlah'])->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('dibatalkan', $batch->status);
        $this->assertSame('salah input jumlah', $batch->alasan_batal);
        $this->assertEqualsWithDelta(2, $this->stok($this->tepung), 0.0001);
        $this->assertEqualsWithDelta(3, $this->stok($this->cabai), 0.0001);
        $this->assertEqualsWithDelta(5, $this->stok($this->tomat), 0.0001);
        $this->assertEqualsWithDelta(0, $this->stok($this->saus), 0.0001);
        // Nilai FIFO bahan kembali utuh: 2×10.000 + 3×40.000 + 5×20.000
        $nilai = StockBatch::whereIn('item_id', [$this->tepung->id, $this->cabai->id, $this->tomat->id])->where('lokasi_id', $this->gudang->id)
            ->get()->sum(fn ($b) => (float) $b->qty_sisa * (float) $b->harga_beli_per_unit);
        $this->assertEqualsWithDelta(240000, $nilai, 0.5);

        // Batal dua kali → ditolak
        $this->actingAs($owner)->post(route('produksi.batalkan', $batch), ['alasan' => 'lagi'])->assertSessionHas('error');
    }

    public function test_tidak_bisa_batal_kalau_hasil_sudah_terpakai(): void
    {
        $this->produksi();
        $batch = ProduksiBatch::latest('id')->firstOrFail();
        app(StokService::class)->keluar($this->saus->id, $this->gudang->id, 1, 'dipakai', 'uji', null);

        $this->actingAs($this->buatUser('owner'))->post(route('produksi.batalkan', $batch), ['alasan' => 'coba batal'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah terpakai'));
        $this->assertSame('selesai', $batch->fresh()->status);
        $this->assertEqualsWithDelta(3.8, $this->stok($this->saus), 0.0001);
    }

    public function test_hasil_produksi_dipakai_di_komposisi_sate_ayam_dan_hpp_ikut_modal_produksi(): void
    {
        $this->produksi(); // Saus Padang 4,8 liter @15.625

        $sate = Item::create(['kode_item' => 'PRDUJI-SATE' . rand(100, 999), 'nama_item' => 'Sate Ayam Uji', 'tipe' => 'produk_jual',
            'satuan' => 'porsi', 'harga_jual' => 25000, 'harga_beli_terakhir' => 0, 'is_active' => true]);
        $resep = ResepBumbu::create(['nama' => 'Resep Sate Ayam Uji', 'kode' => 'RSU' . rand(1000, 9999), 'item_id' => $sate->id, 'is_active' => true]);
        $resep->items()->create(['item_id' => $this->saus->id, 'qty_per_unit' => 30, 'satuan' => 'ml', 'is_wajib' => true, 'mode_harga' => 'pakai_master', 'urutan' => 1]);

        Kas::create(['cabang_id' => $this->gudang->id, 'nama_kas' => 'Kas Tunai Gudang Uji', 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        $kasir = $this->buatUser('kasir', $this->gudang->id);
        $this->actingAs($kasir);
        $order = app(PenjualanService::class)->buatOrder([
            'kasir_id' => $kasir->id, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway', 'diskon' => 0,
            'items' => [['item_id' => $sate->id, 'qty' => 2, 'harga_satuan' => 25000, 'satuan' => 'porsi', 'nama_item' => $sate->nama_item]],
            'payments' => [['metode' => 'tunai', 'jumlah' => 50000]], 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 50000,
        ], $this->gudang->id);

        // 2 porsi × 30 ml = 0,06 liter Saus Padang; bahan mentahnya TIDAK ikut terpotong lagi
        $this->assertEqualsWithDelta(4.74, $this->stok($this->saus), 0.0001);
        $this->assertEqualsWithDelta(1.5, $this->stok($this->tepung), 0.0001);
        $this->assertEqualsWithDelta(0.06 * 15625, (float) $order->items->first()->hpp, 0.05);
    }

    public function test_halaman_dan_menu_tampil(): void
    {
        $this->produksi();
        $owner = $this->buatUser('owner');

        foreach (['produksi.index', 'produksi.create', 'produksi.resep.index', 'produksi.resep.create'] as $r) {
            $this->actingAs($owner)->get(route($r))->assertOk();
        }
        $resep = ResepProduksi::where('item_id', $this->saus->id)->first();
        $this->actingAs($owner)->get(route('produksi.resep.edit', $resep))->assertOk()->assertSee('Saus Padang Uji');
        $this->actingAs($owner)->get(route('produksi.index'))->assertSee('Saus Padang Uji')->assertSee('<span>Produksi</span>', false);
        $this->actingAs($this->buatUser('kasir', $this->outlet->id))->get(route('dashboard'))->assertDontSee('<span>Produksi</span>', false);

        // Tanpa gudang: halaman tetap terbuka dan memberi petunjuk
        $this->actingAs($this->buatUser('admin_gudang', $this->outlet->id))->get(route('produksi.index'))->assertOk()->assertSee('Belum ada');
    }
}
