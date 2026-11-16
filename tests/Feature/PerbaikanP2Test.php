<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap P porting dimsum — keamanan:
 * 6  Dashboard Semua Cabang / Gudang tidak bisa dibuka lewat alamat langsung
 * 7  Form Karyawan tidak mengubah akun Owner; akun login ikut aturan Manajemen User
 * 17 Kasir tanpa cabang → pesan, bukan error 500 (Layout Meja, Antrian Kasir)
 * 18 Role Owner hanya diberikan / akun Owner hanya diubah oleh Owner/Tim IT
 */
class PerbaikanP2Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet P2 ' . uniqid(), 'kode_cabang' => 'P2' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);
    }

    // ---------------- 6. Dashboard ----------------

    public function test_dashboard_semua_cabang_dan_gudang_tidak_bisa_dibuka_kasir_lewat_alamat(): void
    {
        $kasir = $this->buatUser('kasir', $this->c->id);
        $this->actingAs($kasir)->get(route('dashboard.pusat'))->assertRedirect(route('dashboard'));
        $this->actingAs($kasir)->get(route('dashboard.gudang'))->assertRedirect(route('dashboard.cabang'));
        $this->actingAs($this->buatUser('owner'))->get(route('dashboard.pusat'))->assertOk();
        $this->actingAs($this->buatUser('teknisi'))->get(route('dashboard.pusat'))->assertOk();

        // admin gudang yang ditugaskan di gudang itu tetap bisa
        $gudang = Cabang::gudangPusat()->first();
        if ($gudang) {
            $this->actingAs($this->buatUser('admin_gudang', $gudang->id))->get(route('dashboard.gudang'))->assertOk();
        }
    }

    // ---------------- 7. Karyawan ↔ akun login ----------------

    private function karyawanDenganUser(User $user): Karyawan
    {
        return Karyawan::create([
            'cabang_id' => $this->c->id, 'user_id' => $user->id, 'nik' => 'NIKP2' . rand(10000, 99999), 'nama_lengkap' => 'Karyawan P2', 'jenis_kelamin' => 'L',
            'jabatan' => 'Staf', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => today(), 'gaji_pokok' => 1000000, 'status' => 'aktif',
        ]);
    }

    private function dataKaryawan(Karyawan $k, array $extra = []): array
    {
        return array_merge([
            'cabang_id' => $k->cabang_id, 'nik' => $k->nik, 'nama_lengkap' => $k->nama_lengkap, 'jenis_kelamin' => 'L', 'jabatan' => 'Staf',
            'tipe_karyawan' => 'tetap', 'tanggal_masuk' => today()->toDateString(), 'gaji_pokok' => 1000000, 'status' => 'aktif',
        ], $extra);
    }

    public function test_edit_karyawan_milik_owner_tidak_menurunkan_role_owner(): void
    {
        $ownerAkun = $this->buatUser('owner');
        $k = $this->karyawanDenganUser($ownerAkun);
        $admin = $this->buatUser('admin_pusat');

        $this->actingAs($admin)->get(route('karyawan.edit', $k))->assertOk()->assertDontSee('name="user_role"', false)->assertSee('Ini akun Owner');

        $this->actingAs($admin)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['jabatan' => 'Pemilik']))->assertRedirect(route('karyawan.show', $k));
        $this->assertSame('owner', $ownerAkun->fresh()->role->value);

        $this->actingAs($admin)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['user_role' => 'kasir']))->assertSessionHas('error');
        $this->assertSame('owner', $ownerAkun->fresh()->role->value);
        $this->actingAs($admin)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['user_email' => 'ganti' . uniqid() . '@contoh.id']))->assertSessionHas('error');
        $this->assertSame($ownerAkun->email, $ownerAkun->fresh()->email);
    }

    public function test_ubah_role_akun_karyawan_butuh_izin_edit_user(): void
    {
        $kasirAkun = $this->buatUser('kasir', $this->c->id);
        $k = $this->karyawanDenganUser($kasirAkun);

        // manajer cabang tanpa centangan user.edit → ditolak
        $manajer = $this->buatUser('manajer_cabang', $this->c->id);
        if (! $manajer->can('user.edit')) {
            $this->actingAs($manajer)->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['user_role' => 'operator_produksi']));
            $this->assertSame('kasir', $kasirAkun->fresh()->role->value);
        }

        $this->actingAs($this->buatUser('owner'))->put(route('karyawan.update', $k), $this->dataKaryawan($k, ['user_role' => 'manajer_cabang']))->assertRedirect();
        $this->assertSame('manajer_cabang', $kasirAkun->fresh()->role->value);
    }

    // ---------------- 17. Kasir tanpa cabang ----------------

    public function test_kasir_tanpa_cabang_layout_meja_dan_antrian_tidak_error(): void
    {
        $kasir = $this->buatUser('kasir'); // tanpa cabang
        $this->actingAs($kasir)->get(route('kasir.layout-meja'))->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'belum ditugaskan ke cabang'));
        $this->actingAs($kasir)->get(route('kasir.queue'))->assertRedirect(route('dashboard'));

        // kasir dengan cabang tetap normal; polling cabang lain ditolak
        $kasirCabang = $this->buatUser('kasir', $this->c->id);
        $this->actingAs($kasirCabang)->get(route('kasir.layout-meja'))->assertOk();
        $lain = Cabang::where('tipe', 'cabang')->where('id', '<>', $this->c->id)->value('id');
        $this->actingAs($kasirCabang)->getJson(route('kasir.layout-meja.poll-notifikasi', $this->c->id))->assertOk();
        if ($lain) {
            $this->actingAs($kasirCabang)->getJson(route('kasir.layout-meja.poll-notifikasi', $lain))->assertForbidden();
        }
    }

    // ---------------- 18. Role Owner ----------------

    public function test_role_owner_hanya_diberikan_dan_dikelola_owner(): void
    {
        $admin = $this->buatUser('admin_pusat');
        $this->actingAs($admin)->get(route('user.create'))->assertOk()->assertDontSee('value="owner"', false);
        $this->actingAs($admin)->post(route('user.store'), [
            'name' => 'Owner Palsu', 'email' => 'palsu' . uniqid() . '@contoh.id', 'role' => 'owner',
            'password' => 'RahasiaSekali123', 'password_confirmation' => 'RahasiaSekali123',
        ])->assertSessionHasErrors('role');

        $owner = $this->buatUser('owner');
        foreach ([
            fn () => $this->actingAs($admin)->get(route('user.edit', $owner)),
            fn () => $this->actingAs($admin)->patch(route('user.toggle-aktif', $owner)),
            fn () => $this->actingAs($admin)->post(route('user.reset-password', $owner), ['new_password' => 'abcdefgh1', 'new_password_confirmation' => 'abcdefgh1']),
            fn () => $this->actingAs($admin)->delete(route('user.destroy', $owner)),
        ] as $aksi) {
            $aksi()->assertForbidden();
        }
        $this->assertTrue($owner->fresh()->is_active);
        $this->assertNull($owner->fresh()->deleted_at);

        // Owner tetap bisa memberi role Owner & mengelola akun Owner lain
        $owner2 = $this->buatUser('owner');
        $this->actingAs($owner2)->get(route('user.create'))->assertOk()->assertSee('value="owner"', false);
        $this->actingAs($owner2)->get(route('user.edit', $owner))->assertOk();
        $email = 'owner' . uniqid() . '@contoh.id';
        $this->actingAs($owner2)->post(route('user.store'), [
            'name' => 'Owner Kedua', 'email' => $email, 'role' => 'owner',
            'password' => 'RahasiaSekali123', 'password_confirmation' => 'RahasiaSekali123',
        ])->assertSessionHasNoErrors();
        $this->assertSame('owner', User::where('email', $email)->first()->role->value);

        // Admin pusat tetap bisa mengelola user biasa
        $kasir = $this->buatUser('kasir', $this->c->id);
        $this->actingAs($admin)->get(route('user.edit', $kasir))->assertOk();
    }
}
