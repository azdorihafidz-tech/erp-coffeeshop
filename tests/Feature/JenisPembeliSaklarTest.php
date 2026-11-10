<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Order;
use App\Models\Stock;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 2026-11-10 — saklar "Jenis Pembeli di POS" per cabang (Cabang → Edit), bawaan OFF.
 * OFF: POS gudang/roastery berjualan biasa (Retail), pilihan Jenis Pembeli tidak tampil,
 * dan server menolak Wholesale / Kirim ke Cabang. ON: perilaku lama.
 */
class JenisPembeliSaklarTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $gudang;
    private Cabang $outlet;
    private Item $barang;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $u = rand(10000, 99999);
        $this->gudang = Cabang::create(['nama_cabang' => "Gudang JP {$u}", 'kode_cabang' => "GJP{$u}", 'tipe' => 'gudang_pusat', 'is_active' => true,
            'takeaway_aktif' => true, 'take_away_fee' => 0, 'service_charge_persen' => 0, 'antrian_produksi_aktif' => false]);
        $this->outlet = Cabang::create(['nama_cabang' => "Outlet JP {$u}", 'kode_cabang' => "OJP{$u}", 'tipe' => 'cabang', 'is_active' => true,
            'takeaway_aktif' => true, 'take_away_fee' => 0, 'service_charge_persen' => 0, 'antrian_produksi_aktif' => false]);
        foreach ([$this->gudang, $this->outlet] as $c) {
            Kas::create(['cabang_id' => $c->id, 'nama_kas' => "Kas Tunai {$c->kode_cabang}", 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
                'saldo_awal' => 500000, 'saldo_sekarang' => 500000, 'is_active' => true]);
        }
        $this->barang = Item::create(['kode_item' => "JP-PCK-{$u}", 'nama_item' => 'Pack Uji JP', 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 20000, 'harga_jual' => 30000, 'is_active' => true]);
        $this->owner = $this->buatUser('owner');
        $this->actingAs($this->owner);
        app(StokService::class)->masuk($this->barang->id, $this->gudang->id, 10, 'stok awal', 'uji', null, 20000);
    }

    private function jual(string $customerType, array $ubah = [])
    {
        return $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])
            ->postJson(route('penjualan.store'), array_merge([
                'tipe_transaksi' => 'takeaway', 'customer_type' => $customerType,
                'outlet_tujuan_id' => $customerType === 'internal' ? $this->outlet->id : null,
                'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 30000, 'diskon' => 0,
                'payments' => [['metode' => 'tunai', 'jumlah' => 30000]],
                'items' => [['item_id' => $this->barang->id, 'nama_item' => $this->barang->nama_item, 'qty' => 1, 'harga_satuan' => 30000, 'satuan' => 'pcs']],
            ], $ubah));
    }

    private function stokGudang(): float
    {
        return (float) Stock::where('item_id', $this->barang->id)->where('lokasi_id', $this->gudang->id)->value('qty');
    }

    private function pos()
    {
        return $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->gudang->id])->get(route('penjualan.pos'))->assertOk();
    }

    public function test_bawaan_off_pos_tanpa_jenis_pembeli_dan_kirim_ditolak(): void
    {
        $this->assertFalse((bool) $this->gudang->fresh()->jenis_pembeli_aktif, 'bawaan kolom = OFF');
        $this->assertFalse($this->gudang->fresh()->jenisPembeliTampil());

        $this->pos()->assertDontSee('id="selectCustomerType"', false)->assertDontSee('Kirim ke Cabang (menunggu diterima)');

        foreach (['internal', 'wholesale'] as $jenis) {
            $this->jual($jenis)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'belum dinyalakan'));
        }
        $this->assertEqualsWithDelta(10, $this->stokGudang(), 0.001, 'ditolak = stok utuh');
        $this->assertSame(0, Order::where('cabang_id', $this->gudang->id)->count());

        // Penjualan biasa (tanpa customer_type, seperti form POS saat OFF) tetap jalan sebagai Retail
        $this->jual('retail', ['customer_type' => null])->assertOk()->assertJsonPath('success', true);
        $o = Order::where('cabang_id', $this->gudang->id)->latest('id')->firstOrFail();
        $this->assertSame('retail', $o->customer_type);
        $this->assertEqualsWithDelta(9, $this->stokGudang(), 0.001);
    }

    public function test_on_pos_menampilkan_jenis_pembeli_dan_kirim_jalan(): void
    {
        $this->gudang->update(['jenis_pembeli_aktif' => true]);

        $this->pos()->assertSee('id="selectCustomerType"', false)->assertSee('Kirim ke Cabang (menunggu diterima)')->assertSee($this->outlet->nama_cabang);

        $this->jual('wholesale')->assertOk();
        $this->assertSame('wholesale', Order::where('cabang_id', $this->gudang->id)->latest('id')->value('customer_type'));
        $this->jual('internal')->assertOk();
        $o = Order::where('cabang_id', $this->gudang->id)->latest('id')->firstOrFail();
        $this->assertSame('internal', $o->customer_type);
        $this->assertSame('menunggu', $o->status_kirim);
    }

    public function test_outlet_biasa_tidak_punya_jenis_pembeli_walau_kolom_on(): void
    {
        $this->outlet->update(['jenis_pembeli_aktif' => true]);
        $this->assertFalse($this->outlet->fresh()->jenisPembeliTampil(), 'hanya Gudang Pusat / Roastery');

        $this->actingAs($this->owner)->withSession(['active_cabang_id' => $this->outlet->id])
            ->get(route('penjualan.pos'))->assertOk()->assertDontSee('id="selectCustomerType"', false);
    }

    public function test_saklar_disimpan_dari_form_cabang(): void
    {
        $base = ['nama_cabang' => $this->gudang->nama_cabang, 'kode_cabang' => $this->gudang->kode_cabang, 'tipe' => 'gudang_pusat',
            'is_active' => 1, 'kepala_cabang_id' => ''];

        $html = $this->actingAs($this->owner)->get(route('cabang.edit', $this->gudang))->assertOk()
            ->assertSee('Jenis Pembeli di POS')->getContent();
        $this->assertMatchesRegularExpression('/id="wrapJenisPembeliAktif" class="border-top pt-2 mb-2 "/', $html, 'gudang: saklar tampil');
        $this->assertDoesNotMatchRegularExpression('/name="jenis_pembeli_aktif" value="1"\s+checked/', $html, 'bawaan OFF: tidak tercentang');

        $this->actingAs($this->owner)->put(route('cabang.update', $this->gudang), $base + ['jenis_pembeli_aktif' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($this->gudang->fresh()->jenis_pembeli_aktif);
        $this->pos()->assertSee('id="selectCustomerType"', false);

        // Switch dimatikan → form mengirim hidden 0
        $this->actingAs($this->owner)->put(route('cabang.update', $this->gudang), $base + ['jenis_pembeli_aktif' => 0])->assertRedirect();
        $this->assertFalse($this->gudang->fresh()->jenis_pembeli_aktif);
        $this->pos()->assertDontSee('id="selectCustomerType"', false);

        // Form cabang biasa: saklar disembunyikan (d-none)
        $html = $this->actingAs($this->owner)->get(route('cabang.edit', $this->outlet))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="wrapJenisPembeliAktif" class="[^"]*d-none/', $html);
    }
}
