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
        $gudang = Cabang::where('tipe', 'gudang_pusat')->where('is_active', true)->first();
        $ho = Cabang::create(['nama_cabang' => 'HO Uji Pilih', 'kode_cabang' => 'HOP' . rand(1000, 9999), 'tipe' => 'head_office', 'is_active' => true]);

        $this->actingAs($owner)->withSession(['active_cabang_id' => null])
            ->get(route('penjualan.pos'))->assertOk()
            ->assertSee('Pilih cabang untuk berjualan')
            ->assertSee($outlet->nama_cabang)
            // Outlet, roastery, dan gudang pusat (2026-11-06: gudang memakai POS untuk Kirim ke Cabang).
            // Head Office tidak berjualan.
            ->assertViewHas('pilihanCabang', fn ($c) => $c->contains('id', $outlet->id)
                && (!$gudang || $c->contains('id', $gudang->id))
                && ! $c->contains('id', $ho->id)
                && $c->every(fn ($x) => in_array($x->tipe->value, ['cabang', 'roastery', 'gudang_pusat'], true)));
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

    private function tigaOutlet(): array
    {
        $outlet = Cabang::where('tipe', 'cabang')->where('is_active', true)->orderBy('id')->take(3)->get();
        $this->assertCount(3, $outlet, 'Butuh 3 outlet aktif di database test.');

        return $outlet->all();
    }

    public function test_kasir_dengan_cabang_default_langsung_masuk_pos_cabangnya(): void
    {
        [$a] = $this->tigaOutlet();
        $kasir = $this->buatUser('kasir', $a->id);

        $this->actingAs($kasir)->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('Pilih cabang untuk berjualan')
            ->assertViewHas('cabangId', $a->id);
    }

    public function test_user_tanpa_cabang_default_otomatis_di_cabang_miliknya_bukan_cabang_lain(): void
    {
        [$a, $b, $c] = $this->tigaOutlet();
        $kasir = $this->buatUser('kasir');
        $kasir->cabangs()->attach([$a->id => ['is_default' => false], $b->id => ['is_default' => false]]);

        // CabangMiddleware memasang cabang pertama milik user → tidak pernah "Semua Cabang"
        $this->actingAs($kasir)->get(route('penjualan.pos'))->assertOk()
            ->assertDontSee('Pilih cabang untuk berjualan')
            ->assertViewHas('cabangId', fn ($id) => in_array($id, [$a->id, $b->id], true) && $id !== $c->id)
            // Pilihan ganti cabang di header juga hanya cabang miliknya
            ->assertViewHas('userCabangs', fn ($list) => !$list->contains('id', $c->id));
    }

    public function test_kasir_tidak_bisa_pindah_ke_cabang_lain_atau_semua_cabang(): void
    {
        [$a, , $c] = $this->tigaOutlet();
        $kasir = $this->buatUser('kasir', $a->id);

        // Cabang yang bukan miliknya → ditolak, cabang aktif tidak berubah
        $this->actingAs($kasir)->post(route('cabang.switch'), ['cabang_id' => $c->id])->assertForbidden();
        $this->assertNotEquals($c->id, session('active_cabang_id'));

        // "Semua Cabang" hanya untuk Owner/Admin Pusat
        $this->actingAs($kasir)->post(route('cabang.switch'), ['cabang_id' => null])->assertForbidden();

        $this->actingAs($kasir)->get(route('penjualan.pos'))->assertOk()->assertViewHas('cabangId', $a->id);
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
