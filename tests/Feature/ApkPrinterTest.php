<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint APK Kasir (2026-11-03) — integrasi ERP dengan aplikasi Android
 * "Kopi Drip Kasir". Di browser biasa semua alur cetak lama tetap ada;
 * bagian APK hanya aktif kalau window.KopiDripPrinter disuntik aplikasi.
 */
class ApkPrinterTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabang = Cabang::create([
            'nama_cabang' => 'Outlet APK ' . uniqid(),
            'kode_cabang' => 'APK' . strtoupper(substr(uniqid(), -5)),
            'tipe' => 'cabang', 'is_active' => true, 'dine_in_aktif' => true, 'takeaway_aktif' => true,
        ]);
    }

    private function order(string $kodeKategori, int $qty = 1): Order
    {
        $item = Item::where('tipe', 'produk_jual')
            ->whereHas('category', fn ($q) => $q->where('kode_kategori', $kodeKategori))->firstOrFail();
        $o = Order::create([
            'cabang_id' => $this->cabang->id, 'nomor_order' => 'ORD-APK-' . uniqid(),
            'tanggal_order' => today(), 'tipe_order' => 'produk_jadi', 'tipe_transaksi' => 'takeaway',
            'total_harga' => 20000, 'total_bayar' => 20000, 'jumlah_bayar' => 20000,
            'tipe_pembayaran' => 'tunai', 'status' => 'selesai',
        ]);
        OrderItem::create([
            'order_id' => $o->id, 'item_id' => $item->id, 'nama_item' => $item->nama_item, 'qty' => $qty,
            'satuan' => 'gelas', 'harga_satuan' => 20000, 'total_harga' => 20000 * $qty, 'hpp' => 0,
        ]);

        return $o;
    }

    public function test_layout_memuat_helper_apk_dan_overlay_punya_tombol_apk(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);

        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('window.KDApk', false)
            ->assertSee('KopiDripPrinterResult', false)
            // Menu sidebar pengaturan printer: ada tapi tersembunyi (hanya ditampilkan JS di APK)
            ->assertSee('id="kdApkSettingsLink" class="nav-link" style="display:none"', false)
            ->assertSee('cupLabelApkControls', false)
            ->assertSee('cupLabelWebControls', false)
            // Tombol buka laci punya jalur APK, jalur Web Bluetooth lama tetap ada
            ->assertSee('window.KDApk.bukaLaci()', false)
            ->assertSee('navigator.bluetooth.requestDevice', false);
    }

    public function test_struk_modal_punya_jalur_apk_dan_tetap_punya_jalur_bluetooth_lama(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $order = $this->order('KPI', 2);

        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertSee('id="strukApkGroup"', false)
            ->assertSee('window.strukCetakApk', false)
            ->assertSee('id="strukWebBtGroup"', false)
            ->assertSee('window.strukCetakBluetooth', false)
            ->assertSee(json_encode(route('label-cup.order', $order)), false);
    }

    public function test_struk_modal_tanpa_minuman_berlabel_tidak_mengirim_url_label(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $order = $this->order('SNK');

        $this->actingAs($kasir)->get(route('penjualan.struk-modal', $order))->assertOk()
            ->assertSee('var LABEL_URL = null;', false);
    }

    public function test_detail_order_dan_layout_meja_tetap_normal(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $order = $this->order('KPI');

        $this->actingAs($kasir)->get(route('penjualan.show', $order))->assertOk()->assertSee('window.KDApk', false);
        $this->actingAs($kasir)->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('kasir.layout-meja'))->assertOk();
    }
}
