<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\PemakaianPerlengkapan;
use App\Models\Stock;
use App\Services\StokService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug 19 (temuan smoke test Tahap P, 2026-11-17):
 * - Manajer Cabang membuka Pemakaian Perlengkapan → 500 "Column 'id' in field list is ambiguous".
 * - Simpan pemakaian menerima cabang mana saja (dropdown hanya cabang milik user).
 */
class PemakaianPerlengkapanCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $milik;
    private Cabang $lain;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milik = Cabang::create(['nama_cabang' => 'Outlet PP Milik ' . uniqid(), 'kode_cabang' => 'PPM' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->lain  = Cabang::create(['nama_cabang' => 'Outlet PP Lain ' . uniqid(), 'kode_cabang' => 'PPL' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);
        $this->item  = Item::create([
            'kode_item' => 'PPUJI-' . strtoupper(substr(md5(uniqid()), 0, 8)), 'nama_item' => 'Lap Kain Uji',
            'tipe' => 'bahan_baku', 'jenis' => 'perlengkapan', 'track_stok' => true, 'satuan' => 'pcs',
            'harga_beli_terakhir' => 5000, 'harga_jual' => 0, 'is_active' => true,
        ]);
        foreach ([$this->milik, $this->lain] as $c) {
            app(StokService::class)->masuk($this->item->id, $c->id, 10, 'Stok awal uji', 'adjustment', null, 5000);
        }
    }

    private function stok(Cabang $c): float
    {
        return (float) Stock::where('item_id', $this->item->id)->where('lokasi_id', $c->id)->value('qty');
    }

    public function test_manajer_cabang_bisa_membuka_daftar_dan_hanya_melihat_cabangnya(): void
    {
        PemakaianPerlengkapan::create(['item_id' => $this->item->id, 'cabang_id' => $this->lain->id, 'qty' => 1,
            'tanggal_pemakaian' => now()->toDateString(), 'keterangan' => 'Milik cabang lain']);

        $manajer = $this->buatUser('manajer_cabang', $this->milik->id);

        $this->actingAs($manajer)->get(route('pemakaian-perlengkapan.index'))
            ->assertOk()
            ->assertDontSee('Milik cabang lain');

        $this->actingAs($manajer)->get(route('pemakaian-perlengkapan.create'))
            ->assertOk()
            ->assertSee($this->milik->nama_cabang)
            ->assertDontSee($this->lain->nama_cabang);
    }

    public function test_simpan_ke_cabang_lain_ditolak_dan_stok_utuh(): void
    {
        $manajer = $this->buatUser('manajer_cabang', $this->milik->id);

        $this->actingAs($manajer)->post(route('pemakaian-perlengkapan.store'), [
            'item_id' => $this->item->id, 'cabang_id' => $this->lain->id, 'qty' => 2,
            'tanggal_pemakaian' => now()->toDateString(),
        ])->assertSessionHasErrors('cabang_id');

        $this->assertSame(10.0, $this->stok($this->lain));
        $this->assertSame(0, PemakaianPerlengkapan::where('cabang_id', $this->lain->id)->count());
    }

    public function test_simpan_ke_cabang_sendiri_berhasil_dan_owner_boleh_cabang_mana_saja(): void
    {
        $manajer = $this->buatUser('manajer_cabang', $this->milik->id);

        $this->actingAs($manajer)->post(route('pemakaian-perlengkapan.store'), [
            'item_id' => $this->item->id, 'cabang_id' => $this->milik->id, 'qty' => 2,
            'tanggal_pemakaian' => now()->toDateString(),
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect(route('pemakaian-perlengkapan.index'));
        $this->assertSame(8.0, $this->stok($this->milik));

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->post(route('pemakaian-perlengkapan.store'), [
            'item_id' => $this->item->id, 'cabang_id' => $this->lain->id, 'qty' => 3,
            'tanggal_pemakaian' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(7.0, $this->stok($this->lain));
    }
}
