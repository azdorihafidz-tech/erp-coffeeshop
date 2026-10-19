<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemAttribute;
use App\Models\ItemAttributeValue;
use App\Models\ItemVariant;
use App\Models\Meja;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderQueue;
use App\Services\CupLabelService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Label Cup (2026-10-19) — label stiker gelas: 1 label per gelas,
 * filter kategori per cabang, varian, bill dine-in (item baru saja),
 * hak akses & isolasi cabang, integrasi Save Bill / approve QR / struk.
 */
class CupLabelTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private CupLabelService $svc;
    private Cabang $cabang;
    private Item $kopi;
    private Item $snack;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(CupLabelService::class);
        $this->cabang = Cabang::create([
            'nama_cabang' => 'Outlet Label ' . uniqid(),
            'kode_cabang' => 'LBL' . strtoupper(substr(uniqid(), -5)),
            'tipe' => 'cabang',
            'is_active' => true,
            'dine_in_aktif' => true,
            'takeaway_aktif' => true,
        ]);
        $this->kopi = $this->itemKategori('KPI');
        $this->snack = $this->itemKategori('SNK');
    }

    private function itemKategori(string $kode): Item
    {
        return Item::where('tipe', 'produk_jual')
            ->whereHas('category', fn ($q) => $q->where('kode_kategori', $kode))
            ->firstOrFail();
    }

    private function order(array $baris, array $extra = []): Order
    {
        $o = Order::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'nomor_order' => 'ORD-TSTLBL-' . str_pad((string) ++$this->seq, 4, '0', STR_PAD_LEFT) . uniqid(),
            'tanggal_order' => today(),
            'tipe_order' => 'produk_jadi',
            'tipe_transaksi' => 'takeaway',
            'nama_pelanggan' => 'Budi',
            'total_harga' => 0, 'total_bayar' => 0, 'jumlah_bayar' => 0,
            'tipe_pembayaran' => 'tunai',
            'status' => 'selesai',
        ], $extra));
        foreach ($baris as [$item, $qty, $catatan, $variantId]) {
            OrderItem::create([
                'order_id' => $o->id, 'item_id' => $item->id, 'item_variant_id' => $variantId,
                'nama_item' => $item->nama_item, 'qty' => $qty, 'satuan' => 'gelas',
                'harga_satuan' => 10000, 'total_harga' => 10000 * $qty, 'hpp' => 0, 'catatan' => $catatan,
            ]);
        }

        return $o;
    }

    private function meja(): Meja
    {
        return Meja::create([
            'cabang_id' => $this->cabang->id, 'nomor_meja' => (string) random_int(100, 999), 'nama_meja' => 'Meja Test',
            'kapasitas' => 4, 'lokasi' => 'indoor', 'qr_token' => Meja::generateQrToken(), 'qr_type' => 'permanent', 'status' => 'aktif',
        ]);
    }

    // ------------------------------------------------------------ service

    public function test_satu_label_per_gelas_hanya_kategori_berlabel(): void
    {
        $order = $this->order([[$this->kopi, 2, 'Large, less sugar', null], [$this->snack, 3, null, null]]);

        $hasil = $this->svc->untukOrder($order);

        $this->assertTrue($hasil['aktif']);
        $this->assertCount(2, $hasil['labels']);
        $l = $hasil['labels'][1];
        $this->assertSame(2, $l['ke']);
        $this->assertSame(2, $l['dari']);
        $this->assertSame(mb_strtoupper($this->kopi->nama_item), $l['nama']);
        $this->assertSame('Large, less sugar', $l['catatan']);
        $this->assertSame('Budi', $l['pelanggan']);
        $this->assertStringStartsWith('Take Away · ', $l['info']);
        $this->assertSame(['lebar' => 50, 'tinggi' => 30], $hasil['ukuran']);
    }

    public function test_cabang_nonaktif_tidak_ada_label(): void
    {
        $this->cabang->update(['label_cup_aktif' => false]);
        $hasil = $this->svc->untukOrder($this->order([[$this->kopi, 1, null, null]]));

        $this->assertFalse($hasil['aktif']);
        $this->assertSame([], $hasil['labels']);
    }

    public function test_kategori_diatur_per_cabang(): void
    {
        $this->cabang->update(['label_cup_kategori' => ['SNK'], 'label_cup_lebar_mm' => 40]);
        $hasil = $this->svc->untukOrder($this->order([[$this->kopi, 1, null, null], [$this->snack, 2, null, null]]));

        $this->assertCount(2, $hasil['labels']);
        $this->assertSame(mb_strtoupper($this->snack->nama_item), $hasil['labels'][0]['nama']);
        $this->assertSame(40, $hasil['ukuran']['lebar']);

        $this->cabang->update(['label_cup_kategori' => []]);
        $this->assertSame([], $this->svc->untukOrder($this->order([[$this->kopi, 1, null, null]]))['labels']);
    }

    public function test_opsi_varian_ikut_tercetak(): void
    {
        $attr = ItemAttribute::create(['item_id' => $this->kopi->id, 'nama' => 'Size', 'urutan' => 1]);
        $val = ItemAttributeValue::create(['item_attribute_id' => $attr->id, 'nilai' => 'Large', 'urutan' => 1]);
        $variant = ItemVariant::create(['item_id' => $this->kopi->id, 'stok' => 0, 'is_active' => true]);
        $variant->attributeValues()->attach($val->id);

        $hasil = $this->svc->untukOrder($this->order([[$this->kopi, 1, null, $variant->id]]));

        $this->assertSame(['Size: Large'], $hasil['labels'][0]['opsi']);
    }

    public function test_bill_hanya_item_yang_diminta(): void
    {
        $meja = $this->meja();
        $bill = Bill::open($meja);
        $a = $bill->addItem($this->kopi->id, 1, null, 'Panas', 20000);
        $b = $bill->addItem($this->kopi->id, 2, null, null, 20000);

        $semua = $this->svc->untukBill($bill->fresh());
        $sebagian = $this->svc->untukBill($bill->fresh(), [$b->id]);

        $this->assertCount(3, $semua['labels']);
        $this->assertCount(2, $sebagian['labels']);
        $this->assertStringStartsWith('Dine-in · Meja Test', $sebagian['labels'][0]['info']);
    }

    // ---------------------------------------------------------------- HTTP

    public function test_endpoint_json_dan_html(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $order = $this->order([[$this->kopi, 2, 'Less ice', null]]);

        $this->actingAs($kasir)->getJson(route('label-cup.order', $order))
            ->assertOk()->assertJsonCount(2, 'labels')->assertJsonPath('labels.0.catatan', 'Less ice')
            ->assertJsonStructure(['aktif', 'ukuran', 'labels', 'print_url']);

        $this->actingAs($kasir)->get(route('label-cup.order', ['order' => $order, 'format' => 'html']))
            ->assertOk()->assertSee('size: 50mm 30mm', false)->assertSee(mb_strtoupper($this->kopi->nama_item))->assertSee('1/2');
    }

    public function test_kasir_cabang_lain_ditolak_dan_tanpa_izin_ditolak(): void
    {
        $order = $this->order([[$this->kopi, 1, null, null]]);
        $lain = Cabang::where('id', '!=', $this->cabang->id)->where('tipe', 'cabang')->firstOrFail();

        $this->actingAs($this->buatUser('kasir', $lain->id))->getJson(route('label-cup.order', $order))->assertForbidden();
        $this->actingAs($this->buatUser('helper', $this->cabang->id))->getJson(route('label-cup.order', $order))->assertForbidden();
        auth()->logout();
        $this->get(route('label-cup.order', $order))->assertRedirect();
    }

    public function test_simpan_bill_dine_in_kirim_label_hanya_item_baru(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $meja = $this->meja();
        $payload = fn (Item $item, int $qty) => [
            'tipe_transaksi' => 'dine_in', 'meja_id' => $meja->id,
            'items' => [['item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => $qty, 'harga_satuan' => 20000, 'catatan' => 'Less sugar']],
        ];

        $r1 = $this->actingAs($kasir)->postJson(route('penjualan.simpan-bill'), $payload($this->kopi, 2))->assertOk();
        $this->assertSame(2, $r1->json('label_count'));

        $r2 = $this->actingAs($kasir)->postJson(route('penjualan.simpan-bill'), $payload($this->kopi, 1))->assertOk();
        $this->assertSame(1, $r2->json('label_count'));
        $this->actingAs($kasir)->getJson($r2->json('label_url'))->assertOk()->assertJsonCount(1, 'labels')
            ->assertJsonPath('labels.0.catatan', 'Less sugar');

        $r3 = $this->actingAs($kasir)->postJson(route('penjualan.simpan-bill'), $payload($this->snack, 1))->assertOk();
        $this->assertSame(0, $r3->json('label_count'));
    }

    public function test_simpan_bill_takeaway_menyimpan_catatan_item(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        // Save Bill takeaway mengecek stok (perilaku existing) — isi stok dulu
        \App\Models\Stock::create(['item_id' => $this->snack->id, 'lokasi_id' => $this->cabang->id, 'qty' => 10, 'qty_minimum' => 0]);
        $r = $this->actingAs($kasir)->postJson(route('penjualan.simpan-bill'), [
            'tipe_transaksi' => 'takeaway',
            'items' => [['item_id' => $this->snack->id, 'nama_item' => $this->snack->nama_item, 'qty' => 1, 'harga_satuan' => 20000, 'catatan' => 'Extra shot']],
        ]);
        $this->assertSame(200, $r->status(), (string) $r->getContent());

        $this->assertDatabaseHas('order_items', ['order_id' => $r->json('order_id'), 'catatan' => 'Extra shot']);
    }

    public function test_approve_qr_kirim_info_label(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $meja = $this->meja();
        $queue = OrderQueue::create([
            'meja_id' => $meja->id, 'cabang_id' => $this->cabang->id, 'payment_mode' => 'open_bill', 'status' => 'pending',
            'payload' => [['item_id' => $this->kopi->id, 'qty' => 2, 'catatan' => 'Panas']],
        ]);

        $r = $this->actingAs($kasir)->postJson('/kasir/queue/' . $queue->id . '/approve')->assertOk();

        $this->assertSame(2, $r->json('label_count'));
        $this->assertStringContainsString('/label-cup/bill/', $r->json('label_url'));
    }

    public function test_struk_modal_tampilkan_tombol_label_kecuali_order_dari_bill_meja(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $takeaway = $this->order([[$this->kopi, 2, null, null]]);
        $dariMeja = $this->order([[$this->kopi, 1, null, null]], ['tipe_transaksi' => 'dine_in', 'meja_id' => $this->meja()->id]);

        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $takeaway))->assertOk()->assertSee('Cetak Label Cup (2 gelas)');
        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $dariMeja))->assertOk()->assertDontSee('Cetak Label Cup');
    }

    public function test_pengaturan_cabang_tersimpan_termasuk_kosongkan_kategori(): void
    {
        $owner = $this->buatUser('owner');
        $base = ['nama_cabang' => $this->cabang->nama_cabang, 'kode_cabang' => $this->cabang->kode_cabang, 'tipe' => 'cabang', 'is_active' => 1, 'kepala_cabang_id' => '',
            'label_cup_form' => 1, 'label_cup_aktif' => 1, 'label_cup_lebar_mm' => 40, 'label_cup_tinggi_mm' => 25];

        $this->actingAs($owner)->get(route('cabang.edit', $this->cabang))->assertOk()->assertSee('Label Cup (stiker gelas)');

        $this->actingAs($owner)->put(route('cabang.update', $this->cabang), $base + ['label_cup_kategori' => ['KPI', 'NKP']])->assertRedirect();
        $c = $this->cabang->fresh();
        $this->assertSame(['KPI', 'NKP'], $c->label_cup_kategori);
        $this->assertSame(40, $c->label_cup_lebar_mm);

        $this->actingAs($owner)->put(route('cabang.update', $this->cabang), $base)->assertRedirect();
        $this->assertSame([], $this->cabang->fresh()->label_cup_kategori);
    }

    public function test_pos_dan_detail_order_render_dengan_overlay(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $order = $this->order([[$this->kopi, 1, null, null]]);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.pos'))->assertOk()->assertSee('cupLabelOverlay', false)->assertSee('Catatan item', false);
        $this->actingAs($kasir)->get(route('penjualan.show', $order))->assertOk()->assertSee('Cetak Label Cup');
    }
}
