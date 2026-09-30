<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ResepBumbu;
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

    private function bahan(float $hargaBeli, float $hargaJual = 0): Item
    {
        return Item::create([
            'kode_item' => 'BB-RBH-' . uniqid(), 'nama_item' => 'Bahan Test',
            'tipe' => 'bahan_baku', 'jenis' => 'bahan_baku', 'satuan' => 'kg',
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

    public function test_total_harga_master_nol_kalau_mode_gratis(): void
    {
        $item = $this->bahan(hargaBeli: 20000);
        $bumbu = ResepBumbu::create(['nama' => 'Bumbu Test2', 'kode' => 'BMB-RBH-2', 'is_active' => true, 'dibuat_oleh' => $this->buatUser('owner')->id]);
        $ri = $bumbu->items()->create(['item_id' => $item->id, 'qty_per_unit' => 1, 'satuan' => 'kg', 'mode_harga' => 'gratis', 'is_wajib' => true, 'urutan' => 1]);

        $this->assertEqualsWithDelta(0, $ri->fresh()->total_harga_master, 0.01);
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
