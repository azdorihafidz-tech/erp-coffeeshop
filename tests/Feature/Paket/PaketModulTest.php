<?php

namespace Tests\Feature\Paket;

use App\Support\Paket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Porting dimsum 4.53 Tahap B — modul aktif per instalasi. */
class PaketModulTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function semuaKecuali(string ...$mati): array
    {
        return array_values(array_diff(array_keys(Paket::MODUL), $mati));
    }

    public function test_bawaan_semua_modul_tanpa_batas(): void
    {
        $this->assertSame('Lengkap', Paket::namaPaket());
        $this->assertTrue(Paket::data()['segel_valid']);
        $this->assertSame('tanpa_batas', Paket::statusLangganan()['kode']);
        $this->assertCount(17, Paket::MODUL);
    }

    public function test_peta_izin_coffeeshop(): void
    {
        $this->assertSame('roastery', Paket::modulUntukIzin('petani.view'));
        $this->assertSame('meja_qr', Paket::modulUntukIzin('kasir.queue.approve'));
        $this->assertSame('meja_qr', Paket::modulUntukIzin('master.meja.view'));
        $this->assertSame('penjualan', Paket::modulUntukIzin('master.resep_bumbu.view'));
        $this->assertSame('stok', Paket::modulUntukIzin('master.bahan_baku.view'));
        $this->assertSame('keuangan', Paket::modulUntukIzin('closing.unlock'));
        $this->assertNull(Paket::modulUntukIzin('keuangan.view'), 'Kas & Transaksi = inti');
        $this->assertNull(Paket::modulUntukIzin('cabang.view'));
        $this->assertSame('meja_qr', Paket::modulUntukRoute('order.menu'), 'menu QR pelanggan ikut modul');
        $this->assertSame('akuntansi', Paket::modulUntukRoute('laporan.laba-rugi-formal.index'));
        $this->assertSame('analisis', Paket::modulUntukRoute('laporan.laba-rugi.index'));
        $this->assertNull(Paket::modulUntukRoute('keuangan.index'));
        // Setiap izin yang ada di database: dipetakan ke modul yang dikenal, atau inti
        foreach (DB::table('permissions')->pluck('name') as $izin) {
            $m = Paket::modulUntukIzin($izin);
            $this->assertTrue($m === null || isset(Paket::MODUL[$m]), $izin);
        }
    }

    public function test_modul_mati_hilang_untuk_semua_termasuk_owner_dan_kembali_saat_dinyalakan(): void
    {
        $owner = $this->buatUser('owner');
        $gudang = $this->buatUser('admin_gudang');
        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertSee(route('petani.index'), false);

        Paket::simpan($this->semuaKecuali('roastery'), null, 7, null);
        $this->assertFalse($owner->hasPermission('petani.view'));
        $this->assertFalse($owner->can('petani.view'));
        $this->assertFalse($gudang->hasPermission('petani.view'));
        $this->assertNotContains('petani.view', $gudang->getPermissions());
        $this->assertNotContains('petani.view', $owner->getPermissions());
        $this->assertTrue($owner->hasPermission('stok.view'), 'modul lain tetap');
        $this->assertTrue($owner->hasPermission('cabang.view'), 'inti tetap');

        $this->actingAs($owner)->followingRedirects()->get(route('dashboard'))->assertDontSee(route('petani.index'), false);
        $this->actingAs($owner)->get(route('petani.index'))->assertForbidden()->assertSee('tidak termasuk paket');
        $this->actingAs($owner)->getJson(route('petani.index'))->assertForbidden()->assertJson(['ok' => false]);
        $this->actingAs($this->buatUser('teknisi'))->get(route('petani.index'))->assertForbidden();

        Paket::simpan(array_keys(Paket::MODUL), null, 7, null);
        $this->assertTrue($gudang->hasPermission('petani.view'), 'centangan lama kembali');
        $this->actingAs($owner)->get(route('petani.index'))->assertOk();
    }

    public function test_meja_qr_mati_menutup_menu_qr_pelanggan(): void
    {
        $meja = DB::table('mejas')->join('cabangs', 'cabangs.id', '=', 'mejas.cabang_id')->select('mejas.*', 'cabangs.kode_cabang')->first();
        if (! $meja) {
            $this->markTestSkipped('Tidak ada meja di DB tes');
        }
        $url = route('order.menu', [$meja->kode_cabang, $meja->nomor_meja]) . '?token=' . $meja->qr_token;
        Paket::simpan($this->semuaKecuali('meja_qr'), null, 7, null);
        $this->get($url)->assertForbidden()->assertSee('tidak termasuk paket');
    }

    public function test_role_hak_akses_menyembunyikan_dan_tidak_menghapus_centangan_modul_mati(): void
    {
        $owner = $this->buatUser('owner');
        $idPetani = DB::table('permissions')->where('name', 'petani.view')->value('id');
        $idStok = DB::table('permissions')->where('name', 'stok.view')->value('id');
        $this->assertTrue(DB::table('role_permissions')->where('role', 'admin_gudang')->where('permission_id', $idPetani)->exists());

        Paket::simpan($this->semuaKecuali('roastery'), null, 7, null);
        $this->actingAs($owner)->get(route('role.index'))->assertOk()->assertDontSee('petani.view');
        $this->actingAs($owner)->post(route('role.update-permissions', 'admin_gudang'), ['permission_ids' => [$idStok]])->assertSessionHas('success');
        $this->assertTrue(DB::table('role_permissions')->where('role', 'admin_gudang')->where('permission_id', $idPetani)->exists(), 'centangan tersembunyi tetap');
    }

    public function test_ketergantungan_dan_nama_paket(): void
    {
        $hasil = Paket::simpan(['produksi'], null, 7, null);
        $this->assertEqualsCanonicalizing(['produksi', 'stok'], $hasil['baru']['modul']);
        $this->assertSame('Kustom', Paket::namaPaket());
        Paket::simpan(['penjualan', 'stok'], null, 7, null);
        $this->assertSame('Dasar', Paket::namaPaket());
        Paket::simpan(['akuntansi'], null, 7, null);
        $this->assertTrue(Paket::aktif('keuangan'));
        Paket::simpan(['meja_qr'], null, 7, null);
        $this->assertTrue(Paket::aktif('penjualan'));
        Paket::simpan(['kiriman'], null, 7, null);
        $this->assertTrue(Paket::aktif('penjualan') && Paket::aktif('stok'));
    }

    public function test_halaman_paket_khusus_tim_it_dan_tercatat(): void
    {
        $t = $this->buatUser('teknisi');
        $this->actingAs($this->buatUser('owner'))->get(route('paket.index'))->assertForbidden();
        $this->actingAs($t)->get(route('paket.index'))->assertOk()->assertSee('Lengkap')->assertSee('Meja &amp; Pesan QR', false)->assertSee('Roastery');
        $this->actingAs($t)->post(route('paket.update'), ['modul' => ['penjualan', 'stok'], 'masa_tenggang' => 7])->assertSessionHas('success');
        Paket::lupakan();
        $this->assertSame('Dasar', Paket::namaPaket());
        $this->assertTrue(DB::table('activity_log')->where('log_name', 'TimIT')->where('description', 'like', 'Ubah paket%')->exists());
    }

    public function test_segel_rusak_mode_lunak(): void
    {
        DB::table('lisensi')->update(['modul' => json_encode(['penjualan'])]); // diubah langsung di database
        Paket::lupakan();
        $this->assertFalse(Paket::data()['segel_valid']);
        $this->assertFalse(Paket::bacaSaja(), 'mode lunak: tidak dikunci');
        $this->assertFalse(Paket::aktif('stok'), 'isi yang ada tetap dipakai');

        $t = $this->buatUser('teknisi');
        $this->assertSame(1, min(1, Paket::peringatkanSegelRusak()));
        $this->assertSame(0, Paket::peringatkanSegelRusak(), 'sekali per hari');
        $this->actingAs($t)->get(route('paket.index'))->assertOk()->assertSee('Segel lisensi tidak cocok');

        $this->actingAs($t)->post(route('paket.update'), ['modul' => array_keys(Paket::MODUL), 'masa_tenggang' => 7]);
        Paket::lupakan();
        $this->assertTrue(Paket::data()['segel_valid']);
    }
}
