<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ResepBumbu;
use App\Models\ResepBumbuItem;
use App\Models\StockBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Fix (2026-10-02): Master Bumbu Pusat & Import dari Bumbu Pusat
 * WAJIB pakai harga_beli_terakhir (modal), bukan harga_jual (harga
 * customer). Detail cost per cabang di modal Import pakai FIFO stock_batches
 * terlebih dahulu, fallback ke harga_beli_terakhir global.
 */
class ResepBumbuHargaBeliTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function bahan(float $hargaBeli, float $hargaJual = 0, string $satuan = 'kg'): Item
    {
        return Item::create([
            'kode_item' => 'BB-RBH-' . uniqid(), 'nama_item' => 'Bahan Test',
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => $satuan,
            'harga_beli_terakhir' => $hargaBeli, 'harga_jual' => $hargaJual, 'is_active' => true,
        ]);
    }

    public function test_total_harga_master_pakai_harga_beli_bukan_harga_jual(): void
    {
        // harga_beli != harga_jual supaya test ini gagal kalau kode regresi
        // balik pakai harga_jual lagi.
        $item = $this->bahan(hargaBeli: 20000, hargaJual: 99999);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Test', 'kode' => 'BMB-RBH-1', 'is_active' => true, 'dibuat_oleh' => $this->buatUser('owner')->id]);
        $ri = $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);

        $this->assertEqualsWithDelta(20000, $ri->fresh()->total_harga_master, 0.01);
    }

    /**
     * 2026-10-30: pilihan Gratis/Pakai Master dihapus — data lama yang masih
     * tersimpan 'gratis' TETAP dihitung sebagai modal (dulu Rp0).
     */
    public function test_data_lama_mode_gratis_tetap_dihitung_modal(): void
    {
        $item = $this->bahan(hargaBeli: 20000);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Test2', 'kode' => 'BMB-RBH-2', 'is_active' => true, 'dibuat_oleh' => $this->buatUser('owner')->id]);
        $ri = $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'gratis', 'is_wajib' => true, 'urutan' => 1]);

        $this->assertEqualsWithDelta(20000, $ri->fresh()->total_harga_master, 0.01);
    }

    public function test_preview_dan_detail_bumbu_pusat_hitung_bahan_mode_gratis(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(hargaBeli: 10000);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Gratis Lama', 'kode' => 'BMB-RBH-7', 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        // 200 g x Rp10.000/kg = Rp2.000 per porsi
        $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 200, 'satuan' => 'g', 'mode_harga' => 'gratis', 'is_wajib' => true, 'urutan' => 1]);

        $this->actingAs($owner)->postJson(route('master.produk-jual.preview-bumbu', $bumbu), ['qty_per_unit' => 1, 'jumlah' => 3])
            ->assertOk()->assertJsonPath('subtotal', fn ($v) => abs($v - 6000) < 0.01);

        $data = $this->actingAs($owner)->get(route('master.produk-jual.bumbu-pusat.detail', $bumbu))->assertOk()->json();
        $this->assertCount(1, $data['bahan']);
    }

    /**
     * Bug 2026-11-04: di halaman Edit, baris resep lama dimuat sebelum const/let
     * yang dipakai perhitungannya dibuat → ReferenceError, subtotal kosong dan
     * hanya 1 baris termuat (menyimpan form menghapus baris sisanya).
     */
    public function test_edit_produk_baris_resep_lama_dimuat_setelah_semua_deklarasi(): void
    {
        $owner = $this->buatUser('owner');
        $produk = Item::where('tipe', 'produk_jual')->firstOrFail();

        $html = $this->actingAs($owner)->get(route('master.produk-jual.edit', $produk))->assertOk()->getContent();

        $muat = strrpos($html, 'RESEP_AWAL.forEach(r => tambahBarisResep(r));');
        $this->assertNotFalse($muat);
        foreach (['let linkedRowSeq', 'const linkedSubtotalTimeout', 'const BUMBU_PUSAT_DETAIL_URL_BASE', 'let bumbuTerpilih'] as $deklarasi) {
            $this->assertLessThan($muat, strpos($html, $deklarasi), "$deklarasi harus dibuat sebelum baris resep lama dimuat");
        }
        $this->assertSame(1, substr_count($html, 'RESEP_AWAL.forEach('));
    }

    public function test_form_tidak_lagi_menampilkan_pilihan_mode_harga(): void
    {
        $owner = $this->buatUser('owner');
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Form', 'kode' => 'BMB-RBH-8', 'is_active' => true, 'dibuat_oleh' => $owner->id]);

        $this->actingAs($owner)->get(route('master.resep-bumbu.edit', $bumbu))->assertOk()
            ->assertDontSee('Mode Harga')->assertDontSee('Gratis (include)');
        $this->actingAs($owner)->get(route('master.produk-jual.create'))->assertOk()
            ->assertDontSee('Mode Harga')->assertDontSee('Pakai Master');
    }

    /** 2026-10-30: kalkulator HPP mengonversi takaran gram/ml ke satuan stok. */
    public function test_kalkulator_hpp_konversi_gram_ke_kg(): void
    {
        $owner = $this->buatUser('owner');
        $biji = $this->bahan(hargaBeli: 83333); // per kg
        $cup = $this->bahan(hargaBeli: 1500);   // per pcs
        $produk = Item::where('tipe', 'produk_jual')->firstOrFail();

        $data = $this->actingAs($owner)->postJson(route('master.produk-jual.kalkulator-resep', $produk), [
            'jumlah' => 2,
            'resep' => [
                ['item_id' => $biji->id, 'qty_per_unit' => 9, 'satuan' => 'g'],
                ['item_id' => $cup->id, 'qty_per_unit' => 1, 'satuan' => 'pcs'],
            ],
        ])->assertOk()->json();

        // 2 gelas: 18 g = 0,018 kg × 83.333 = 1.500 ; cup 2 × 1.500 = 3.000
        $this->assertEqualsWithDelta(4500, $data['total_hpp'], 0.5);
    }

    /** 2026-11-03: konversi ikut satuan stok bahan, resep lama (stok kg/liter) tidak berubah. */
    public function test_konversi_takaran_ikut_satuan_stok_bahan(): void
    {
        $k = fn ($q, $s, $st) => ResepBumbuItem::konversiKeSatuanStok($q, $s, $st);

        $this->assertEqualsWithDelta(10, $k(10, 'gram', 'gram'), 1e-9);
        $this->assertEqualsWithDelta(10, $k(10, 'g', 'gr'), 1e-9);
        $this->assertEqualsWithDelta(0.01, $k(10, 'gram', 'kg'), 1e-9);
        $this->assertEqualsWithDelta(0.15, $k(150, 'ml', 'liter'), 1e-9);
        $this->assertEqualsWithDelta(150, $k(150, 'ml', 'ml'), 1e-9);
        $this->assertEqualsWithDelta(500, $k(0.5, 'kg', 'gram'), 1e-9);
        $this->assertEqualsWithDelta(100, $k(1, 'ons', 'gram'), 1e-9);
        // Satuan stok tak dikenal → aturan lama
        $this->assertEqualsWithDelta(0.01, $k(10, 'g', null), 1e-9);
        $this->assertEqualsWithDelta(0.02, $k(0.02, 'botol', 'botol'), 1e-9);
        $this->assertEqualsWithDelta(1, $k(1, 'pcs', 'kg'), 1e-9);
    }

    /** Bug 2026-11-03: bahan stok gram dipilih di komposisi → subtotal Rp0 & stok terpotong 1/1000. */
    public function test_bahan_stok_gram_subtotal_dan_kebutuhan_stok_benar(): void
    {
        $owner = $this->buatUser('owner');
        $gula = $this->bahan(hargaBeli: 15, satuan: 'gram'); // Rp15 per gram
        $produk = Item::where('tipe', 'produk_jual')->firstOrFail();

        $data = $this->actingAs($owner)->postJson(route('master.produk-jual.kalkulator-resep', $produk), [
            'jumlah' => 2,
            'resep' => [['item_id' => $gula->id, 'qty_per_unit' => 10, 'satuan' => 'gram']],
        ])->assertOk()->json();
        $this->assertEqualsWithDelta(300, $data['total_hpp'], 0.01); // 2 × 10 g × Rp15

        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Gram', 'kode' => 'BMB-RBH-10', 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        $ri = $bumbu->items()->create(['item_id' => $gula->id, 'qty_per_unit' => 10, 'satuan' => 'gram', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);
        $ri = $ri->fresh();

        $this->assertEqualsWithDelta(150, $ri->total_harga_master, 0.01);
        // Stok yang dipotong POS per 1 unit = 10 gram (bukan 0,01)
        $this->assertEqualsWithDelta(10, $ri->expandKeBahanMentah()[0]['butuh_kg'], 1e-9);
    }

    public function test_tambah_bahan_bumbu_pusat_tanpa_mode_harga(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(hargaBeli: 8000);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Tambah', 'kode' => 'BMB-RBH-9', 'is_active' => true, 'dibuat_oleh' => $owner->id]);

        $this->actingAs($owner)->post(route('master.resep-bumbu.items.store', $bumbu), [
            'item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'is_wajib' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(8000, $bumbu->items()->firstOrFail()->total_harga_master, 0.01);
    }

    public function test_halaman_resep_bumbu_edit_tampilkan_harga_beli(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(hargaBeli: 15000, hargaJual: 88888);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Tampil', 'kode' => 'BMB-RBH-3', 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);

        $response = $this->actingAs($owner)->get(route('master.resep-bumbu.edit', $bumbu));
        $response->assertOk();
        $response->assertSee('Harga Beli (Modal)');
        $response->assertSee('15.000', false);
        $response->assertDontSee('88.888', false);
    }

    public function test_detail_bumbu_pusat_pakai_fifo_kalau_ada_batch(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(hargaBeli: 10000); // fallback global
        $cabang = Cabang::orderBy('id')->firstOrFail();
        StockBatch::create([
            'item_id' => $item->id, 'lokasi_id' => $cabang->id,
            'qty_awal' => 5, 'qty_sisa' => 5, 'harga_beli_per_unit' => 25000,
            'tanggal_masuk' => now()->subDay(),
        ]);

        $bumbu = ResepBumbu::create(['nama' => 'Bumbu FIFO', 'kode' => 'BMB-RBH-4', 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);

        $response = $this->actingAs($owner)->get(route('master.produk-jual.bumbu-pusat.detail', $bumbu));
        $response->assertOk();
        $data = $response->json();

        $this->assertEqualsWithDelta(25000, $data['bahan'][0]['per_cabang'][(string) $cabang->id]['harga'], 0.01);
        $this->assertTrue($data['bahan'][0]['per_cabang'][(string) $cabang->id]['is_fifo']);
    }

    public function test_detail_bumbu_pusat_fallback_harga_beli_terakhir_kalau_tanpa_batch(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(hargaBeli: 12345);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu NoBatch', 'kode' => 'BMB-RBH-5', 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);

        $response = $this->actingAs($owner)->get(route('master.produk-jual.bumbu-pusat.detail', $bumbu));
        $response->assertOk();
        $data = $response->json();

        foreach ($data['cabangs'] as $c) {
            $this->assertEqualsWithDelta(12345, $data['bahan'][0]['per_cabang'][(string) $c['id']]['harga'], 0.01);
            $this->assertFalse($data['bahan'][0]['per_cabang'][(string) $c['id']]['is_fifo']);
        }
    }

    public function test_detail_bumbu_pusat_total_per_cabang_terhitung_benar(): void
    {
        $owner = $this->buatUser('owner');
        $item = $this->bahan(hargaBeli: 10000);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Total', 'kode' => 'BMB-RBH-6', 'is_active' => true, 'dibuat_oleh' => $owner->id]);
        // 500 g = 0.5 kg x Rp10.000/kg = Rp5.000
        $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 500, 'satuan' => 'g', 'mode_harga' => 'pakai_master', 'is_wajib' => true, 'urutan' => 1]);

        $response = $this->actingAs($owner)->get(route('master.produk-jual.bumbu-pusat.detail', $bumbu));
        $data = $response->json();

        $cabangId = $data['cabangs'][0]['id'];
        $this->assertEqualsWithDelta(5000, $data['total_per_cabang'][(string) $cabangId], 0.01);
    }
}
