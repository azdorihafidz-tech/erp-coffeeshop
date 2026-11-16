<?php

namespace Tests\Feature\Paket;

use App\Models\Pelanggan;
use App\Support\Paket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Porting dimsum 4.53 Tahap B — masa aktif, masa tenggang, mode baca-saja. */
class PaketLanggananTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function berakhir(Carbon $tanggal, int $tenggang = 7): void
    {
        Paket::simpan(array_keys(Paket::MODUL), $tanggal->toDateString(), $tenggang, null);
    }

    private function dataPelanggan(string $nama): array
    {
        return ['nama_pelanggan' => $nama, 'kode_pelanggan' => 'PLG-T' . rand(100000, 999999), 'telepon' => '0812' . rand(1000000, 9999999)];
    }

    public function test_status_langganan_bertahap(): void
    {
        $sampai = Carbon::parse('2027-01-31');
        $this->berakhir($sampai);
        $this->assertSame('aktif', Paket::statusLangganan(Carbon::parse('2027-01-01'))['kode']);
        $this->assertSame('segera', Paket::statusLangganan(Carbon::parse('2027-01-17'))['kode']);
        $this->assertSame(14, Paket::statusLangganan(Carbon::parse('2027-01-17'))['sisa_hari']);
        $this->assertSame('segera', Paket::statusLangganan(Carbon::parse('2027-01-31 23:00'))['kode'], 'hari terakhir masih berlaku');
        $this->assertSame('tenggang', Paket::statusLangganan(Carbon::parse('2027-02-01'))['kode']);
        $this->assertSame('tenggang', Paket::statusLangganan(Carbon::parse('2027-02-07'))['kode']);
        $this->assertSame('baca_saja', Paket::statusLangganan(Carbon::parse('2027-02-08'))['kode']);
        $this->berakhir($sampai, 0);
        $this->assertSame('baca_saja', Paket::statusLangganan(Carbon::parse('2027-02-01'))['kode'], 'tanpa masa tenggang');
    }

    public function test_pita_peringatan_per_status(): void
    {
        $owner = $this->buatUser('owner');
        $kasir = $this->buatUser('kasir', \App\Models\Cabang::where('tipe', 'cabang')->orderBy('id')->value('id'));

        $this->berakhir(today()->addDays(10));
        $this->actingAs($owner)->get(route('panduan.index'))->assertSee('Langganan aplikasi berakhir');
        $this->actingAs($kasir)->get(route('panduan.index'))->assertDontSee('Langganan aplikasi berakhir');

        $this->berakhir(today()->subDays(2));
        $this->actingAs($kasir)->get(route('panduan.index'))->assertSee('Masa tenggang sampai');

        $this->berakhir(today()->subDays(30));
        $this->actingAs($kasir)->get(route('panduan.index'))->assertSee('Mode baca-saja');

        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
        $this->actingAs($owner)->get(route('panduan.index'))->assertDontSee('Langganan aplikasi')->assertDontSee('Mode baca-saja');
    }

    public function test_baca_saja_menolak_simpan_tapi_boleh_melihat_dan_unduh(): void
    {
        $owner = $this->buatUser('owner');
        $this->berakhir(today()->subDays(30));
        $nama = 'Pelanggan Baca Saja ' . uniqid();

        $this->actingAs($owner)->post(route('pelanggan.store'), $this->dataPelanggan($nama))
            ->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'baca-saja'));
        $this->assertFalse(Pelanggan::where('nama_pelanggan', $nama)->exists());
        $this->actingAs($owner)->postJson(route('pelanggan.store'), ['nama_pelanggan' => $nama])->assertStatus(423)->assertJson(['ok' => false]);
        // Transaksi POS ditolak
        $this->actingAs($owner)->postJson(route('penjualan.store'), [])->assertStatus(423);

        $this->actingAs($owner)->get(route('pelanggan.index'))->assertOk();
        $this->actingAs($owner)->get(route('keuangan.export'))->assertOk();
        $this->actingAs($owner)->post(route('notifikasi.read-all'))->assertSessionMissing('error');
        $this->actingAs($owner)->post(route('logout'))->assertRedirect();
    }

    public function test_tim_it_tidak_terkena_dan_masa_tenggang_masih_normal(): void
    {
        $this->berakhir(today()->subDays(30));
        $t = $this->buatUser('teknisi');
        $nama = 'Pelanggan Teknisi ' . uniqid();
        $this->actingAs($t)->post(route('pelanggan.store'), $this->dataPelanggan($nama));
        $this->assertTrue(Pelanggan::where('nama_pelanggan', $nama)->exists(), 'Tim IT tetap bisa menyimpan');
        // Perpanjang → normal lagi
        $this->actingAs($t)->post(route('paket.update'), ['modul' => array_keys(Paket::MODUL), 'aktif_sampai' => today()->addYear()->toDateString(), 'masa_tenggang' => 7])->assertSessionHas('success');
        Paket::lupakan();
        $this->assertFalse(Paket::bacaSaja());

        $this->berakhir(today()->subDays(2)); // masih tenggang
        $owner = $this->buatUser('owner');
        $nama2 = 'Pelanggan Tenggang ' . uniqid();
        $this->actingAs($owner)->post(route('pelanggan.store'), $this->dataPelanggan($nama2));
        $this->assertTrue(Pelanggan::where('nama_pelanggan', $nama2)->exists(), 'masa tenggang: masih normal');
    }
}
