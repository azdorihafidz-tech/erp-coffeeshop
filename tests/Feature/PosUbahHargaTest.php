<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCabang;
use App\Models\ItemVariant;
use App\Models\Kas;
use App\Models\Meja;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\User;
use App\Services\BillService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.52 Tahap B (porting dimsum 4.47) — izin `order.ubah_harga`: tanpa izin, Harga/Unit
 * dari form DIABAIKAN server (harga produk / harga cabang / harga varian). Berlaku di POS
 * Charge, Save Bill takeaway, Save Bill dine-in, Kirim ke Cabang. Bayar Bill tetap memakai
 * harga yang sudah tersimpan di Bill.
 */
class PosUbahHargaTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $cabang;
    private Item $kopi;

    protected function setUp(): void
    {
        parent::setUp();
        $u = rand(10000, 99999);
        $this->cabang = Cabang::create(['nama_cabang' => "Outlet Harga {$u}", 'kode_cabang' => "HRG{$u}", 'tipe' => 'cabang',
            'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true]);
        Kas::create(['cabang_id' => $this->cabang->id, 'nama_kas' => "Kas Tunai {$u}", 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        $this->kopi = Item::create(['kode_item' => "HRG-KPI-{$u}", 'nama_item' => 'Kopi Uji Harga', 'tipe' => 'produk_jual', 'satuan' => 'gelas',
            'harga_beli_terakhir' => 5000, 'harga_jual' => 20000, 'is_active' => true]);
        $this->actingAs($this->buatUser('owner'));
        app(StokService::class)->masuk($this->kopi->id, $this->cabang->id, 100, 'stok awal', 'uji', null, 5000);
    }

    private function kasir(): User
    {
        return $this->buatUser('kasir', $this->cabang->id);
    }

    private function beriIzin(string $role): void
    {
        DB::table('role_permissions')->insertOrIgnore([
            'role' => $role, 'permission_id' => Permission::where('name', 'order.ubah_harga')->value('id'),
        ]);
        Cache::forget("role_permissions_{$role}");
    }

    private function charge(User $user, float $harga, array $extra = []): Order
    {
        $total = $harga * 2;
        $r = $this->actingAs($user)->withSession(['active_cabang_id' => $this->cabang->id])->postJson(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 100000,
            'payments' => [['metode' => 'tunai', 'jumlah' => 100000]],
            'items' => [array_merge(['item_id' => $this->kopi->id, 'nama_item' => $this->kopi->nama_item, 'qty' => 2,
                'harga_satuan' => $harga, 'satuan' => 'gelas'], $extra)],
        ]);
        $this->assertSame(200, $r->status(), (string) $r->getContent());

        return Order::where('cabang_id', $this->cabang->id)->latest('id')->firstOrFail();
    }

    private function meja(): Meja
    {
        return Meja::create(['cabang_id' => $this->cabang->id, 'nomor_meja' => (string) random_int(100, 999), 'nama_meja' => 'Meja Harga',
            'kapasitas' => 4, 'lokasi' => 'indoor', 'qr_token' => Meja::generateQrToken(), 'qr_type' => 'permanent', 'status' => 'aktif']);
    }

    public function test_izin_ada_dan_tidak_dicentang_untuk_role_mana_pun(): void
    {
        $id = Permission::where('name', 'order.ubah_harga')->value('id');
        $this->assertNotNull($id, 'izin order.ubah_harga ter-seed');
        $this->assertSame(0, DB::table('role_permissions')->where('permission_id', $id)->count());
        $this->assertArrayHasKey('order.ubah_harga', \App\Services\UpdateDatabaseService::PERMISSION_BARU);
        $this->assertSame([], \App\Services\UpdateDatabaseService::PERMISSION_BARU['order.ubah_harga']);
    }

    public function test_kasir_tanpa_izin_harga_form_diabaikan(): void
    {
        $order = $this->charge($this->kasir(), 1000);
        $row = $order->items()->firstOrFail();
        $this->assertEquals(20000, (float) $row->harga_satuan);
        $this->assertEquals(40000, (float) $row->total_harga);
        $this->assertEquals(40000, (float) $order->total_bayar);
    }

    public function test_owner_dan_kasir_berizin_memakai_harga_form(): void
    {
        $this->assertEquals(15000, (float) $this->charge($this->buatUser('owner'), 15000)->items()->value('harga_satuan'));

        $this->beriIzin('kasir');
        $this->assertEquals(12000, (float) $this->charge($this->kasir(), 12000)->items()->value('harga_satuan'));
    }

    public function test_harga_cabang_dan_harga_varian_dipakai_tanpa_izin(): void
    {
        ItemCabang::create(['item_id' => $this->kopi->id, 'cabang_id' => $this->cabang->id, 'harga_override' => 18000, 'is_active' => true]);
        $this->assertEquals(18000, (float) $this->charge($this->kasir(), 1000)->items()->value('harga_satuan'), 'harga per cabang');

        $varian = ItemVariant::create(['item_id' => $this->kopi->id, 'sku' => 'HRG-L-' . uniqid(), 'harga_override' => 25000, 'is_active' => true]);
        $order = $this->charge($this->kasir(), 1000, ['item_variant_id' => $varian->id]);
        $this->assertEquals(25000, (float) $order->items()->value('harga_satuan'), 'harga varian');

        $varianTanpaHarga = ItemVariant::create(['item_id' => $this->kopi->id, 'sku' => 'HRG-M-' . uniqid(), 'harga_override' => null, 'is_active' => true]);
        $this->assertEquals(20000, (float) $this->charge($this->kasir(), 1000, ['item_variant_id' => $varianTanpaHarga->id])->items()->value('harga_satuan'),
            'varian tanpa harga sendiri = harga_jual produk (sama seperti tampilan POS)');
    }

    public function test_save_bill_takeaway_terkunci(): void
    {
        $r = $this->actingAs($this->kasir())->withSession(['active_cabang_id' => $this->cabang->id])->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'takeaway',
            'items' => [['item_id' => $this->kopi->id, 'nama_item' => $this->kopi->nama_item, 'qty' => 1, 'harga_satuan' => 500]],
        ]);
        $this->assertSame(200, $r->status(), (string) $r->getContent());
        $this->assertEquals(20000, (float) OrderItem::where('order_id', $r->json('order_id'))->value('harga_satuan'));
    }

    public function test_save_bill_dine_in_terkunci_dan_bayar_bill_memakai_harga_bill(): void
    {
        $kasir = $this->kasir();
        $meja = $this->meja();
        $r = $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'dine_in', 'meja_id' => $meja->id,
            'items' => [['item_id' => $this->kopi->id, 'nama_item' => $this->kopi->nama_item, 'qty' => 2, 'harga_satuan' => 500]],
        ]);
        $this->assertSame(200, $r->status(), (string) $r->getContent());
        $bill = Bill::where('meja_id', $meja->id)->where('status', 'open')->firstOrFail();
        $this->assertEquals(20000, (float) BillItem::where('bill_id', $bill->id)->value('harga_satuan'));

        // Owner (berizin) memasukkan harga khusus ke bill → kasir yang membayar tetap memakai harga bill
        $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $this->cabang->id])->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'dine_in', 'meja_id' => $meja->id,
            'items' => [['item_id' => $this->kopi->id, 'nama_item' => $this->kopi->nama_item, 'qty' => 1, 'harga_satuan' => 15000]],
        ])->assertOk();
        $this->assertEquals([15000.0, 20000.0], BillItem::where('bill_id', $bill->id)->orderBy('harga_satuan')->pluck('harga_satuan')->map(fn ($h) => (float) $h)->all());

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->postJson(route('penjualan.bill.charge-bill', $bill), ['payments' => [['metode' => 'tunai', 'jumlah' => 55000]]])->assertOk();
        $order = Order::where('cabang_id', $this->cabang->id)->latest('id')->firstOrFail();
        $this->assertEquals(55000, (float) $order->items()->sum('total_harga'), '2×20.000 + 1×15.000 dari bill, bukan dikunci ulang');
    }

    public function test_tambah_item_layout_meja_tetap_harga_produk(): void
    {
        $kasir = $this->kasir();
        $bill = app(BillService::class)->tandaiMejaTerisi($this->meja()->id, $kasir->id);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->postJson(route('kasir.bill.tambah-item', $bill->id), ['item_id' => $this->kopi->id, 'qty' => 1])->assertOk();
        $this->assertEquals(20000, (float) BillItem::where('bill_id', $bill->id)->value('harga_satuan'));
    }

    public function test_tampilan_pos_terkunci_untuk_kasir_tidak_untuk_owner(): void
    {
        $html = $this->actingAs($this->kasir())->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();
        $this->assertStringContainsString('window.POS_BOLEH_UBAH_HARGA = false;', $html);
        $this->assertStringContainsString('aria-label="Harga/Unit Terkunci"', $html, 'tooltip harga tampil');

        $html = $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();
        $this->assertStringContainsString('window.POS_BOLEH_UBAH_HARGA = true;', $html);
    }

    public function test_kirim_ke_cabang_terkunci_untuk_admin_pusat(): void
    {
        $u = rand(10000, 99999);
        $gudang = Cabang::create(['nama_cabang' => "Gudang Harga {$u}", 'kode_cabang' => "GHR{$u}", 'tipe' => 'gudang_pusat', 'is_active' => true]);
        Kas::create(['cabang_id' => $gudang->id, 'nama_kas' => "Kas Tunai G{$u}", 'tipe_kas' => 'tunai', 'default_untuk' => 'tunai',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
        app(StokService::class)->masuk($this->kopi->id, $gudang->id, 10, 'stok awal', 'uji', null, 5000);

        $r = $this->actingAs($this->buatUser('admin_pusat'))->withSession(['active_cabang_id' => $gudang->id])->postJson(route('penjualan.store'), [
            'tipe_transaksi' => 'takeaway', 'customer_type' => 'internal', 'outlet_tujuan_id' => $this->cabang->id,
            'tipe_pembayaran' => 'tunai', 'jumlah_bayar' => 0, 'payments' => [['metode' => 'tunai', 'jumlah' => 1]],
            'items' => [['item_id' => $this->kopi->id, 'nama_item' => $this->kopi->nama_item, 'qty' => 3, 'harga_satuan' => 1000, 'satuan' => 'gelas']],
        ]);
        $this->assertSame(200, $r->status(), (string) $r->getContent());
        $kiriman = Order::where('cabang_id', $gudang->id)->latest('id')->firstOrFail();
        $this->assertEquals(20000, (float) $kiriman->items()->value('harga_satuan'));
        $this->assertEquals(60000, (float) $kiriman->total_bayar);
    }
}
