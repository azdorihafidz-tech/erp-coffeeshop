<?php

namespace Tests\Feature\StokOpname;

use App\Enums\PenyebabSelisihStok;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Stock;
use App\Models\StokOpname;
use App\Models\TransaksiKeuangan;
use App\Services\StokOpnameService;
use App\Services\StokService;

/**
 * Penyesuaian Stok Opname untuk coffeeshop (RANCANGAN_PORTING_DIMSUM.md bagian 7):
 * barang global (bukan per cabang), pack Roastery ikut, Head Office tidak bisa
 * diopname, label "Kulkas / pendingin mati".
 */
class StokOpnameCoffeeshopTest extends StokOpnameTestCase
{
    private function lokasi(string $tipe): Cabang
    {
        return Cabang::create(['nama_cabang' => 'Lokasi ' . $tipe . ' ' . uniqid(), 'kode_cabang' => 'SOC' . rand(10000, 99999),
            'tipe' => $tipe, 'is_active' => true]);
    }

    private function item(string $nama, string $tipe, array $extra = []): Item
    {
        return Item::create(array_merge([
            'kode_item' => 'SOC' . strtoupper(substr(md5($nama . uniqid()), 0, 8)), 'nama_item' => $nama, 'tipe' => $tipe,
            'satuan' => 'pcs', 'harga_beli_terakhir' => 1000, 'harga_jual' => 0, 'is_active' => true, 'track_stok' => true,
        ], $extra));
    }

    public function test_daftar_barang_global_pack_roastery_ikut_produk_berkomposisi_tidak(): void
    {
        $roastery = $this->lokasi('roastery');
        $stok = app(StokService::class);

        $bahan = $this->item('Gula Aren Uji', 'bahan_baku');                         // fisik aktif → ikut walau stok 0
        $tanpaLacak = $this->item('Tisu Uji', 'tambahan_gratis', ['track_stok' => false]); // tidak dilacak → tidak ikut
        $pack = $this->item('Pack 250g Uji', 'produk_jual', ['harga_jual' => 75000]);     // punya stok → ikut
        $menu = $this->item('Kopi Susu Uji', 'produk_jual', ['harga_jual' => 20000]);     // berkomposisi, tanpa stok → tidak
        $nonaktifBerstok = $this->item('Sirup Lama Uji', 'bahan_baku', ['is_active' => false]); // stok masih ada → ikut
        $nonaktifKosong = $this->item('Sirup Habis Uji', 'bahan_baku', ['is_active' => false]); // tidak ikut
        $stok->masuk($pack->id, $roastery->id, 5, 'uji', null, null, 30000);
        $stok->masuk($nonaktifBerstok->id, $roastery->id, 2, 'uji', null, null, 5000);

        $ids = StokOpnameService::barangLokasi($roastery->id)->pluck('items.id');
        $this->assertTrue($ids->contains($bahan->id), 'bahan fisik aktif');
        $this->assertTrue($ids->contains($pack->id), 'pack Roastery berstok');
        $this->assertTrue($ids->contains($nonaktifBerstok->id), 'barang nonaktif yang stoknya masih ada');
        $this->assertFalse($ids->contains($menu->id), 'produk jual tanpa stok sendiri');
        $this->assertFalse($ids->contains($tanpaLacak->id), 'tidak dilacak stoknya');
        $this->assertFalse($ids->contains($nonaktifKosong->id), 'nonaktif tanpa stok');

        // Pack hanya punya stok di Roastery → di outlet lain tidak ikut
        $this->assertFalse(StokOpnameService::barangLokasi($this->cabang->id)->pluck('items.id')->contains($pack->id));
    }

    public function test_opname_pack_di_roastery_sampai_disetujui(): void
    {
        $roastery = $this->lokasi('roastery');
        $pack = $this->item('Pack Arabika Uji', 'produk_jual', ['harga_jual' => 75000]);
        app(StokService::class)->masuk($pack->id, $roastery->id, 10, 'uji', null, null, 30000);

        $this->actingAs($this->admin)->post(route('stok-opname.store'), [
            'cabang_id' => $roastery->id, 'cakupan' => 'barang', 'cakupan_ids' => [$pack->id], 'penghitung' => [$this->admin->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $o = StokOpname::latest('id')->firstOrFail();
        $this->assertSame($roastery->id, $o->cabang_id);

        $this->postJson(route('stok-opname.hitung', $o), ['item_id' => $pack->id, 'qty' => 9])->assertOk();
        $this->post(route('stok-opname.rincian', $o), ['item_id' => $pack->id, 'rincian' => [['penyebab' => 'sampel', 'qty' => 1]]])
            ->assertSessionHasNoErrors();
        $this->post(route('stok-opname.ajukan', $o))->assertSessionHasNoErrors();
        // Admin ikut menghitung → pakai penyetuju lain (Owner boleh)
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->post(route('stok-opname.setujui', $o))->assertSessionHas('success');

        $this->assertSame('disetujui', $o->fresh()->status);
        $this->assertEquals(9.0, (float) Stock::where('item_id', $pack->id)->where('lokasi_id', $roastery->id)->value('qty'));
        $tk = TransaksiKeuangan::where('referensi_type', 'stok_opname')->where('referensi_id', $o->id)->firstOrFail();
        $this->assertNull($tk->kas_id, 'kerugian tanpa kas');
        $this->assertEquals(30000.0, (float) $tk->jumlah, 'nilai = harga pokok FIFO pack');
    }

    public function test_head_office_tidak_bisa_diopname(): void
    {
        $ho = $this->lokasi('head_office');
        $bahan = $this->item('Kertas Uji', 'bahan_baku');

        $this->actingAs($this->admin)->get(route('stok-opname.create'))->assertOk()
            ->assertViewHas('lokasi', fn ($l) => ! $l->contains('id', $ho->id) && $l->contains('id', $this->cabang->id));
        $this->actingAs($this->admin)->post(route('stok-opname.store'), [
            'cabang_id' => $ho->id, 'cakupan' => 'barang', 'cakupan_ids' => [$bahan->id],
        ])->assertSessionHas('error');
        $this->assertSame(0, StokOpname::where('cabang_id', $ho->id)->count());

        // Gudang Pusat & outlet tetap bisa
        $this->assertTrue(Cabang::bisaOpname()->whereKey($this->lokasi('gudang_pusat')->id)->exists());
        $this->assertFalse(Cabang::bisaOpname()->whereKey($ho->id)->exists());
    }

    public function test_label_kulkas_dan_tetap_masuk_stok_rusak(): void
    {
        $p = PenyebabSelisihStok::FreezerMati;
        $this->assertSame('freezer_mati', $p->value, 'nilai tersimpan sama dengan dimsum');
        $this->assertStringContainsString('Kulkas', $p->label());
        $this->assertStringContainsString('Kulkas', $p->labelPendek());
        $this->assertTrue($p->keStokRusak());
        $this->assertSame('OPN-RUSAK', $p->kodeKategori());
    }
}
