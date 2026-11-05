<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\Permission;
use App\Support\CabangLaporan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.49 Tahap 0 — pengaman sebelum laporan baru: detail order & struk hanya
 * untuk cabang sendiri, Laporan Penjualan lama tidak bisa mengintip cabang
 * lain lewat ?cabang_id=, dan Update Database tidak mencentang ulang izin.
 */
class AksesOrderCabangTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $a;
    private Cabang $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->cabangBaru('A');
        $this->b = $this->cabangBaru('B');
    }

    private function cabangBaru(string $h): Cabang
    {
        return Cabang::create([
            'nama_cabang' => "Cabang AOC {$h} " . uniqid(),
            'kode_cabang' => substr("AO{$h}" . strtoupper(substr(uniqid(), -6)), 0, 10),
            'tipe' => 'cabang',
            'is_active' => true,
        ]);
    }

    private function order(Cabang $c, float $total, array $extra = []): Order
    {
        return Order::create($extra + [
            'cabang_id' => $c->id,
            'nomor_order' => 'TST-AOC-' . uniqid() . mt_rand(10, 99),
            'tanggal_order' => '2025-02-10',
            'tipe_order' => 'produk_jadi',
            'tipe_transaksi' => 'takeaway',
            'total_harga' => $total, 'diskon' => 0, 'total_bayar' => $total, 'jumlah_bayar' => $total,
            'tipe_pembayaran' => 'tunai',
            'status' => 'selesai',
        ]);
    }

    public function test_cabang_laporan_role_cabang_selalu_cabang_sendiri(): void
    {
        $kasir = $this->buatUser('kasir', $this->a->id);
        $this->actingAs($kasir);

        session(['active_cabang_id' => $this->b->id]); // sesi basi / dipaksa
        $this->assertSame($this->a->id, CabangLaporan::id());
        session(['active_cabang_id' => null]);
        $this->assertSame($this->a->id, CabangLaporan::id(), 'Role cabang tidak pernah "Semua Cabang".');
        $this->assertTrue(CabangLaporan::bolehAkses($this->a->id));
        $this->assertFalse(CabangLaporan::bolehAkses($this->b->id));

        $tanpaCabang = $this->buatUser('kasir');
        $this->actingAs($tanpaCabang);
        $this->assertSame(-1, CabangLaporan::id());

        $owner = $this->buatUser('owner');
        $this->actingAs($owner);
        session(['active_cabang_id' => null]);
        $this->assertNull(CabangLaporan::id(), 'Owner: Semua Cabang.');
        session(['active_cabang_id' => $this->b->id]);
        $this->assertSame($this->b->id, CabangLaporan::id());
    }

    public function test_struk_dan_detail_order_hanya_cabang_sendiri(): void
    {
        $milikA = $this->order($this->a, 10000);
        $milikB = $this->order($this->b, 20000);
        $kasirA = $this->buatUser('kasir', $this->a->id);

        foreach (['penjualan.show', 'penjualan.struk', 'penjualan.struk-modal'] as $r) {
            $this->actingAs($kasirA)->get(route($r, $milikA))->assertOk();
            $this->actingAs($kasirA)->get(route($r, $milikB))->assertForbidden();
        }

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('penjualan.struk-modal', $milikB))->assertOk();
        $this->actingAs($owner)->get(route('penjualan.show', $milikB))->assertOk();
    }

    public function test_role_tanpa_izin_order_ditolak(): void
    {
        $o = $this->order($this->a, 10000);
        $role = 'helper';
        DB::table('role_permissions')->where('role', $role)->delete();
        \Illuminate\Support\Facades\Cache::forget('all_permission_names');
        $helper = $this->buatUser($role, $this->a->id);

        $this->actingAs($helper)->get(route('penjualan.struk-modal', $o))->assertForbidden();
    }

    public function test_order_internal_roastery_boleh_dibuka_outlet_tujuan(): void
    {
        $roastery = $this->cabangBaru('R');
        $internal = $this->order($roastery, 50000, ['customer_type' => 'internal', 'outlet_tujuan_id' => $this->a->id]);
        $manajerA = $this->buatUser('manajer_cabang', $this->a->id);
        $manajerB = $this->buatUser('manajer_cabang', $this->b->id);

        $this->actingAs($manajerA)->get(route('penjualan.show', $internal))->assertOk();
        $this->actingAs($manajerB)->get(route('penjualan.show', $internal))->assertForbidden();
    }

    public function test_laporan_penjualan_lama_tidak_bisa_intip_cabang_lain(): void
    {
        $this->order($this->a, 10000);
        $this->order($this->b, 90000);
        $manajerA = $this->buatUser('manajer_cabang', $this->a->id);
        $param = ['dari' => '2025-02-01', 'sampai' => '2025-02-28'];

        $this->actingAs($manajerA)->get(route('laporan.penjualan', $param + ['cabang_id' => $this->b->id]))
            ->assertOk()
            ->assertViewHas('totalOmzet', fn ($v) => (float) $v === 10000.0)
            ->assertViewHas('cabangs', fn ($c) => $c->pluck('id')->all() === [$this->a->id])
            ->assertDontSee('<option value="">Semua Cabang</option>', false);

        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('laporan.penjualan', $param + ['cabang_id' => $this->b->id]))
            ->assertOk()->assertViewHas('totalOmzet', fn ($v) => (float) $v === 90000.0);
    }

    public function test_update_database_tidak_mencentang_ulang_izin_yang_dicabut(): void
    {
        $label = Permission::where('name', 'label_cup.print')->value('id');
        DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $label)->delete();

        $this->actingAs($this->buatUser('owner'))->post(route('update-database.execute'))
            ->assertOk()->assertSee('UPDATE BERHASIL');

        $this->assertFalse(
            DB::table('role_permissions')->where('role', 'kasir')->where('permission_id', $label)->exists(),
            'Izin yang sudah ada & dicabut Owner tidak boleh dicentang lagi.'
        );
    }
}
