<?php

namespace Tests\Feature;

use App\Models\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Update Database production (2026-10-19) — halaman Owner-only untuk
 * migrate + seeder konten tanpa menghapus data / mereset hak akses role.
 */
class UpdateDatabaseTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_hanya_owner(): void
    {
        $this->actingAs($this->buatUser('admin_pusat'))->get(route('update-database.form'))->assertForbidden();
        $this->actingAs($this->buatUser('admin_pusat'))->post(route('update-database.execute'))->assertForbidden();
        $this->actingAs($this->buatUser('owner'))->get(route('update-database.form'))->assertOk()
            ->assertSee('Update Database')->assertSee('label_cup.print')->assertSee('Tidak ada migration baru');
    }

    public function test_tambah_hak_akses_baru_tanpa_reset_hak_akses_lama(): void
    {
        $closingView = Permission::where('name', 'closing.view')->value('id');
        $orderView = Permission::where('name', 'order.view')->value('id');

        // Kondisi production: permission baru belum ada sama sekali (dibuat
        // oleh PermissionSeeder saat update), Owner pernah memberi kasir
        // closing.view & mencabut order.view manual.
        $lama = Permission::where('name', 'label_cup.print')->value('id');
        DB::table('role_permissions')->where('permission_id', $lama)->delete();
        DB::table('permissions')->where('id', $lama)->delete();
        DB::table('role_permissions')->insertOrIgnore(['role' => 'kasir', 'permission_id' => $closingView]);
        DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $orderView)->delete();
        $jumlahTransaksi = DB::table('transaksi_keuangans')->count();

        $this->actingAs($this->buatUser('owner'))->post(route('update-database.execute'))
            ->assertOk()->assertSee('UPDATE BERHASIL');

        $label = Permission::where('name', 'label_cup.print')->value('id');
        $this->assertNotNull($label, 'PermissionSeeder harus membuat permission baru');
        $kasir = fn ($pid) => DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $pid)->exists();
        $this->assertTrue($kasir($label), 'Permission baru harus ditambahkan');
        $this->assertTrue($kasir($closingView), 'Hak akses tambahan Owner tidak boleh dihapus');
        $this->assertFalse($kasir($orderView), 'Hak akses yang dicabut Owner tidak boleh dikembalikan');
        $this->assertSame($jumlahTransaksi, DB::table('transaksi_keuangans')->count());
        $this->assertDatabaseHas('panduan', ['slug' => 'update-database']);
    }
}
