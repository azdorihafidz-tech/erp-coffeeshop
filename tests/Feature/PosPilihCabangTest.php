<?php

namespace Tests\Feature;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Bug 2026-11-03: POS dibuka saat cabang aktif = "Semua Cabang" → 500
 * (Item::tersediaDiCabang(null)). Sekarang POS meminta pilih cabang dulu.
 */
class PosPilihCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_pos_mode_semua_cabang_menampilkan_pilihan_cabang_bukan_error(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $gudang = Cabang::where('tipe', 'gudang_pusat')->first();

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('Pilih cabang untuk berjualan')
            ->assertSee($outlet->nama_cabang)
            // Hanya outlet & roastery (yang berjualan), bukan gudang pusat
            ->assertViewHas('pilihanCabang', fn ($c) => $c->contains('id', $outlet->id)
                && (!$gudang || !$c->contains('id', $gudang->id))
                && $c->every(fn ($x) => in_array($x->tipe->value, ['cabang', 'roastery'], true)));
    }

    public function test_pilih_cabang_lalu_pos_terbuka_untuk_cabang_itu(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->from(route('penjualan.pos'))
            ->post(route('cabang.switch'), ['cabang_id' => $outlet->id])
            ->assertRedirect(route('penjualan.pos'));

        $this->actingAs($owner)->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('Pilih cabang untuk berjualan');
    }

    public function test_simpan_transaksi_tanpa_cabang_ditolak_dengan_pesan(): void
    {
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->postJson(route('penjualan.simpan-bill'), ['items' => [['nama_item' => 'X', 'qty' => 1, 'harga_satuan' => 1000]]])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Pilih cabang dulu (cabang aktif masih Semua Cabang), lalu ulangi transaksi.');
    }
}
