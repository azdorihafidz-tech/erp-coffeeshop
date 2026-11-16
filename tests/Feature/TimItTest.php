<?php

namespace Tests\Feature;

use App\Enums\RoleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/** Porting dimsum 4.53 Tahap A — akun Tim IT (Teknisi) & Aktivitas Tim IT. */
class TimItTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_teknisi_akses_penuh_termasuk_halaman_khusus_owner(): void
    {
        $t = $this->buatUser('teknisi');
        $this->assertTrue($t->aksesPenuh());
        $this->assertTrue($t->hasPermission('closing.unlock'));
        $this->assertTrue($t->can('cabang.delete'));
        $this->assertTrue($t->canAccessAllBranches());
        $this->assertContains('closing.unlock', $t->getPermissions());

        foreach (['role.index', 'pengaturan.umum', 'tim-it.index', 'update-database.form', 'hari-libur.index', 'audit-log.index', 'admin.panduan.index'] as $r) {
            $this->actingAs($t)->get(route($r))->assertOk();
        }
        // Sidebar Tim IT memuat menu Tim IT, Aktivitas Tim IT, Update Database
        $this->actingAs($t)->get(route('tim-it.index'))->assertSee(route('aktivitas-tim-it.index'), false)
            ->assertSee(route('update-database.form'), false)->assertSee('Role &amp; Hak Akses', false);
    }

    public function test_owner_tidak_berubah(): void
    {
        $owner = $this->buatUser('owner');
        $this->assertTrue($owner->aksesPenuh());
        $this->assertFalse($owner->isTeknisi());
        foreach (['role.index', 'pengaturan.umum', 'update-database.form', 'hari-libur.index'] as $r) {
            $this->actingAs($owner)->get(route($r))->assertOk();
        }
        $kasir = $this->buatUser('kasir');
        $this->assertFalse($kasir->aksesPenuh());
        $this->actingAs($kasir)->get(route('update-database.form'))->assertForbidden();
        $this->actingAs($kasir)->get(route('hari-libur.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('tim-it.index'))->assertForbidden();
    }

    public function test_teknisi_tersembunyi_dari_pelanggan(): void
    {
        $t = $this->buatUser('teknisi');
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('user.index'))->assertOk()->assertDontSee($t->email);
        $this->actingAs($owner)->get(route('user.index', ['search' => $t->email]))->assertOk()->assertViewHas('users', fn ($u) => $u->total() === 0);
        $this->actingAs($owner)->get(route('user.show', $t))->assertNotFound();
        $this->actingAs($owner)->get(route('user.edit', $t))->assertNotFound();
        $this->actingAs($owner)->patch(route('user.toggle-aktif', $t))->assertNotFound();
        $this->actingAs($owner)->post(route('user.reset-password', $t), ['new_password' => 'abcdefgh1', 'new_password_confirmation' => 'abcdefgh1'])->assertNotFound();
        $this->actingAs($owner)->delete(route('user.destroy', $t))->assertNotFound();
        $this->assertTrue($t->fresh()->is_active);

        $this->actingAs($owner)->get(route('role.index'))->assertOk()->assertDontSee('Tim IT');
        $this->actingAs($owner)->get(route('user.create'))->assertOk()->assertDontSee('value="teknisi"', false);
        $this->assertNotContains('teknisi', RoleUser::nilaiUntukPelanggan());
        $this->assertNotContains('owner', RoleUser::nilaiUntukPelanggan(false));
        $this->assertFalse(User::pelanggan()->whereKey($t->id)->exists());

        // Owner tidak bisa membuat user ber-role teknisi atau mengubah izin role teknisi
        $this->actingAs($owner)->post(route('user.store'), [
            'name' => 'Palsu', 'email' => 'palsu' . uniqid() . '@contoh.id', 'role' => 'teknisi',
            'password' => 'RahasiaSekali123', 'password_confirmation' => 'RahasiaSekali123',
        ])->assertSessionHasErrors('role');
        $this->actingAs($owner)->post(route('role.update-permissions', 'teknisi'), ['permission_ids' => []])->assertNotFound();

        // Menu Tim IT tertutup untuk Owner, tidak tampil di sidebar Owner
        $this->actingAs($owner)->get(route('tim-it.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('role.index'))->assertDontSee(route('tim-it.index') . '"', false);
    }

    public function test_kelola_akun_tim_it(): void
    {
        $t = $this->buatUser('teknisi');
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => 'it2' . uniqid() . '@contoh.id',
            'password' => 'pendek1', 'password_confirmation' => 'pendek1'])->assertSessionHasErrors('password');
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => 'it2' . uniqid() . '@contoh.id',
            'password' => 'hanyahurufsaja', 'password_confirmation' => 'hanyahurufsaja'])->assertSessionHasErrors('password');
        $email = 'it2' . uniqid() . '@contoh.id';
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Dua', 'email' => $email,
            'password' => 'KataSandiPanjang12', 'password_confirmation' => 'KataSandiPanjang12'])->assertSessionHas('success');
        $baru = User::where('email', $email)->firstOrFail();
        $this->assertTrue($baru->isTeknisi());
        $this->actingAs($t)->get(route('tim-it.index'))->assertOk()->assertSee($email);

        // Tidak bisa menonaktifkan diri sendiri
        $this->actingAs($t)->post(route('tim-it.toggle', $t))->assertSessionHas('error');
        $this->actingAs($t)->post(route('tim-it.toggle', $baru))->assertSessionHas('success');
        $this->assertFalse($baru->fresh()->is_active);
        // Ganti kata sandi
        $this->actingAs($t)->post(route('tim-it.password', $baru), ['password' => 'SandiBaruPanjang99', 'password_confirmation' => 'SandiBaruPanjang99'])
            ->assertSessionHas('success');
        $this->assertTrue(\Hash::check('SandiBaruPanjang99', $baru->fresh()->password));
        // Bukan akun Tim IT → 404
        $this->actingAs($t)->post(route('tim-it.toggle', $this->buatUser('kasir')))->assertNotFound();
        $this->actingAs($t)->post(route('tim-it.password', $this->buatUser('owner')), ['password' => 'SandiBaruPanjang99', 'password_confirmation' => 'SandiBaruPanjang99'])->assertNotFound();
    }

    public function test_aktivitas_tim_it_tercatat_dan_hanya_tim_it_yang_melihat(): void
    {
        $t = $this->buatUser('teknisi');
        $mulai = DB::table('activity_log')->where('log_name', 'TimIT')->count();
        $this->actingAs($t)->post(route('tim-it.store'), ['name' => 'IT Tiga', 'email' => 'it3' . uniqid() . '@contoh.id',
            'password' => 'KataSandiPanjang12', 'password_confirmation' => 'KataSandiPanjang12']);
        $tindakan = DB::table('activity_log')->where('log_name', 'TimIT')->where('causer_id', $t->id)->where('description', 'like', 'Tindakan:%')->latest('id')->first();
        $this->assertNotNull($tindakan);
        $this->assertStringNotContainsString('KataSandiPanjang12', (string) $tindakan->properties, 'isi formulir tidak disimpan');
        $this->assertGreaterThan($mulai, DB::table('activity_log')->where('log_name', 'TimIT')->count());

        // Login dicatat
        \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Auth\Events\Login('web', $t, false));
        $this->assertTrue(DB::table('activity_log')->where('log_name', 'TimIT')->where('causer_id', $t->id)->where('description', 'Login')->exists());

        // Khusus Tim IT: Owner pun tidak bisa melihat
        $this->actingAs($t)->get(route('aktivitas-tim-it.index'))->assertOk()->assertSee($t->name);
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('aktivitas-tim-it.index'))->assertForbidden();
        $this->actingAs($this->buatUser('admin_pusat'))->get(route('aktivitas-tim-it.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('role.index'))->assertDontSee(route('aktivitas-tim-it.index'), false);

        // Audit Log pelanggan tidak memuat catatan Tim IT maupun perubahan oleh akun Tim IT
        $this->actingAs($owner)->get(route('audit-log.index', ['log_name' => 'TimIT']))->assertOk()->assertDontSee('Tindakan: tim-it.store');
        $this->actingAs($owner)->get(route('audit-log.index'))->assertOk()->assertDontSee('it3');
        $this->actingAs($owner)->get(route('audit-log.show', $tindakan->id))->assertNotFound();
        $this->actingAs($t)->get(route('audit-log.show', $tindakan->id))->assertOk();
        $this->actingAs($t)->get(route('audit-log.index', ['log_name' => 'TimIT']))->assertOk()->assertSee('Tindakan: tim-it.store');

        // Tindakan Owner tidak masuk catatan Tim IT
        $jumlah = DB::table('activity_log')->where('log_name', 'TimIT')->count();
        $this->actingAs($owner)->post(route('tim-it.store'), ['name' => 'x']);
        $this->assertSame($jumlah, DB::table('activity_log')->where('log_name', 'TimIT')->count());
    }

    public function test_rollback_migration_ditolak_kalau_ada_akun_tim_it(): void
    {
        $this->buatUser('teknisi');
        $m = require base_path('database/migrations/2026_11_16_100001_add_teknisi_to_users_role_enum.php');
        $this->expectException(\RuntimeException::class);
        $m->down();
    }
}
