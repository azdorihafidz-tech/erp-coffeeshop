<?php

namespace Tests\Feature;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tampilan POS untuk HP (gerobak, 2026-11-06): bilah keranjang melayang, kartu
 * Pelanggan dilipat, produk 3 kolom — HANYA lewat CSS media query ≤ 575px dan
 * JS yang membaca keranjang. Pastikan elemen & alur POS lama tetap ada.
 */
class PosTampilanHpTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_pos_memuat_tampilan_hp_tanpa_mengubah_elemen_lama(): void
    {
        $cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $res = $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $cabang->id])
            ->get(route('penjualan.pos'))->assertOk();

        // Elemen baru tampilan HP
        $res->assertSee('id="posBarHp"', false)
            ->assertSee('id="posBarKeranjang"', false)->assertSee('id="posBarBayar"', false)
            ->assertSee('id="cardPelanggan"', false)->assertSee('id="cardItemOrder"', false)->assertSee('id="cardRingkasan"', false)
            ->assertSee('@media (max-width: 575.98px)', false)
            ->assertSee('#posBarHp { display: none; }', false); // di luar HP disembunyikan

        // Elemen & alur lama tetap ada (tablet/PC tidak berubah)
        $res->assertSee('id="formPos"', false)->assertSee('id="btnProses"', false)->assertSee('id="btnSaveBill"', false)
            ->assertSee('id="gridProduk"', false)->assertSee('id="itemContainer"', false)->assertSee('id="totalBayarDisplay"', false)
            ->assertSee('id="btnModeDesktop"', false)->assertSee('id="btnModeTablet"', false)
            ->assertSee('Cek Antrian')->assertSee('PROSES ORDER');
    }

    public function test_panduan_pos_menjelaskan_tampilan_hp(): void
    {
        $this->seed(\Database\Seeders\PanduanKontenSeeder::class);
        $this->assertStringContainsString('Memakai POS di HP', (string) \App\Models\Panduan::where('slug', 'pos')->value('konten'));
        $this->assertStringContainsString('Label terpotong / tidak pas di stiker', (string) \App\Models\Panduan::where('slug', 'label-cup')->value('konten'));
    }
}
