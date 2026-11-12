<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\ResepBumbu;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug WONGKO PI 2026-11-12: Edit Produk Jual + tambah komposisi pertama kali → 500
 * "Data too long for column 'kode'" karena kode resep = "RESEP-" + kode_item
 * (kode_item boleh 30 huruf, kolom kode resep 20).
 */
class KomposisiKodeResepTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private User $owner;
    private Item $bahan;
    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $u = rand(10000, 99999);
        $this->cabang = Cabang::create(['nama_cabang' => "Outlet KR {$u}", 'kode_cabang' => "OKR{$u}", 'tipe' => 'cabang', 'is_active' => true]);
        $this->bahan = Item::create(['kode_item' => "KR-SUSU-{$u}", 'nama_item' => 'Susu Uji KR', 'tipe' => 'bahan_baku', 'satuan' => 'liter',
            'harga_beli_terakhir' => 18000, 'harga_jual' => 0, 'is_active' => true]);
        $this->owner = $this->buatUser('owner');
    }

    private function produk(string $kode): Item
    {
        return Item::create(['kode_item' => $kode, 'nama_item' => 'ICED PANDAN AREN LATTE ' . rand(100, 999), 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 0, 'harga_jual' => 25000, 'is_active' => true]);
    }

    private function simpan(Item $p)
    {
        return $this->actingAs($this->owner)->put(route('master.produk-jual.update', $p), [
            'kode_item' => $p->kode_item, 'nama_item' => $p->nama_item, 'tipe' => 'produk_jual', 'satuan' => 'pcs',
            'harga_jual' => 25000, 'is_active' => 1,
            'cabang_aktif' => [$this->cabang->id],
            'resep' => [['item_id' => $this->bahan->id, 'qty_per_unit' => '150', 'satuan' => 'ml', 'is_wajib' => '1']],
        ]);
    }

    public function test_kode_produk_panjang_bisa_simpan_komposisi(): void
    {
        $p = $this->produk('KP-ICED PANDAN ' . rand(100, 999)); // 18 huruf → "RESEP-" + kode = 24 > 20
        $this->simpan($p)->assertRedirect(route('master.produk-jual.index'))->assertSessionHasNoErrors();

        $resep = ResepBumbu::where('item_id', $p->id)->firstOrFail();
        $this->assertSame('RESEP-ID' . $p->id, $resep->kode);
        $this->assertLessThanOrEqual(ResepBumbu::KODE_MAKS, mb_strlen($resep->kode));
        $this->assertSame(1, $resep->items()->count());

        // Simpan lagi (resep sudah ada) tetap aman, kode tidak berubah
        $this->simpan($p)->assertRedirect(route('master.produk-jual.index'));
        $this->assertSame('RESEP-ID' . $p->id, $resep->fresh()->kode);
    }

    public function test_kode_pendek_tetap_pola_lama_dan_bentrok_diberi_akhiran(): void
    {
        $p = $this->produk('KR-' . rand(1000, 9999));
        $this->simpan($p)->assertRedirect(route('master.produk-jual.index'));
        $this->assertSame('RESEP-' . $p->kode_item, ResepBumbu::where('item_id', $p->id)->value('kode'), 'kode pendek: pola lama');

        // Kode bentrok dengan resep lain → RESEP-ID{id}, lalu akhiran -2
        $q = $this->produk('KR-' . rand(1000, 9999));
        ResepBumbu::create(['nama' => 'Bentrok 1', 'kode' => 'RESEP-' . $q->kode_item, 'is_active' => true]);
        ResepBumbu::create(['nama' => 'Bentrok 2', 'kode' => 'RESEP-ID' . $q->id, 'is_active' => true]);
        $this->assertSame('RESEP-ID' . $q->id . '-2', ResepBumbu::kodeUntukItem($q));
    }

    public function test_halaman_edit_produk_tanpa_komposisi_tidak_null(): void
    {
        $p = $this->produk('KR-' . rand(1000, 9999));
        $this->actingAs($this->owner)->get(route('master.produk-jual.edit', $p))->assertOk()
            ->assertSee('const RESEP_AWAL = [];', false)
            ->assertDontSee('const RESEP_AWAL = null;', false);
    }
}
