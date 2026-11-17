<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\CherryPurchase;
use App\Models\Item;
use App\Models\Karyawan;
use App\Models\Meja;
use App\Models\Petani;
use App\Services\RoastingBatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Temuan saat menulis Kamus Menu (Tahap D, 2026-11-17):
 * bug 20 Laporan Absensi tanpa cek izin, bug 21 Roastery terkunci kode RST001,
 * saklar pesan lewat QR per outlet di Cabang → Edit.
 */
class PerbaikanTahapDTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function cabang(string $tipe, string $kode = null, bool $aktif = true): Cabang
    {
        return Cabang::create(['nama_cabang' => 'Lokasi ' . $tipe . ' ' . uniqid(), 'kode_cabang' => $kode ?? ('TD' . rand(10000, 99999)),
            'tipe' => $tipe, 'is_active' => $aktif]);
    }

    // ===================== Bug 20 =====================

    public function test_laporan_absensi_butuh_izin_dan_tanpa_cabang_tidak_melihat_cabang_lain(): void
    {
        $lain = $this->cabang('cabang');
        $k = Karyawan::create(['cabang_id' => $lain->id, 'nik' => 'TD' . rand(10000, 99999), 'nama_lengkap' => 'Karyawan Rahasia TD',
            'jenis_kelamin' => 'L', 'jabatan' => 'Staf', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => '2026-01-01', 'gaji_pokok' => 1000000, 'status' => 'aktif']);
        Absensi::create(['karyawan_id' => $k->id, 'cabang_id' => $lain->id, 'tanggal' => now()->toDateString(), 'status' => 'hadir']);

        // Kasir tanpa izin laporan_absensi → 403 untuk halaman & export
        $kasir = $this->buatUser('kasir', $lain->id);
        \Illuminate\Support\Facades\DB::table('role_permissions')->where('role', 'kasir')
            ->whereIn('permission_id', \Illuminate\Support\Facades\DB::table('permissions')->where('name', 'laporan_absensi')->pluck('id'))->delete();
        \Illuminate\Support\Facades\Cache::forget('role_permissions_kasir');
        $this->actingAs($kasir)->get(route('laporan.absensi'))->assertForbidden();
        $this->actingAs($kasir)->get(route('laporan.absensi.excel'))->assertForbidden();
        $this->actingAs($kasir)->get(route('laporan.absensi.pdf'))->assertForbidden();

        // Manajer (punya izin) TANPA cabang → tidak melihat absensi cabang mana pun
        $manajer = $this->buatUser('manajer_cabang');
        $this->assertTrue($manajer->can('laporan_absensi'));
        $this->actingAs($manajer)->get(route('laporan.absensi'))->assertOk()
            ->assertViewHas('records', fn ($r) => $r->total() === 0)->assertDontSee('Karyawan Rahasia TD');

        // Manajer cabang itu melihatnya; Owner juga
        $manajerLain = $this->buatUser('manajer_cabang', $lain->id);
        $this->actingAs($manajerLain)->withSession(['active_cabang_id' => $lain->id])->get(route('laporan.absensi'))->assertOk()->assertSee('Karyawan Rahasia TD');
        $this->actingAs($this->buatUser('owner'))->get(route('laporan.absensi', ['cabang_id' => $lain->id]))->assertOk()->assertSee('Karyawan Rahasia TD');
    }

    // ===================== Bug 21 =====================

    public function test_lokasi_roastery_tidak_lagi_terkunci_kode_rst001(): void
    {
        Cabang::where('tipe', 'roastery')->update(['is_active' => false]);
        $this->assertNull(Cabang::lokasiRoastery());
        $this->assertSame(0, RoastingBatchService::roasteryCabangId());
        try {
            RoastingBatchService::pastikanLokasiRoastery();
            $this->fail('Harus ditolak tanpa lokasi Roastery');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Belum ada lokasi Roastery', $e->getMessage());
        }

        // Instalasi tanpa RST001 (go-live / WONGKO PI): Roastery pertama dipakai
        $r1 = $this->cabang('roastery', 'RSTA' . rand(100, 999));
        $r2 = $this->cabang('roastery', 'RSTB' . rand(100, 999));
        $outlet = $this->cabang('cabang');
        $this->assertSame($r1->id, Cabang::lokasiRoastery()->id);
        // Cabang aktif di pojok kanan atas = Roastery → dipakai
        session(['active_cabang_id' => $r2->id]);
        $this->assertSame($r2->id, RoastingBatchService::roasteryCabangId());
        // Cabang aktif bukan Roastery → kembali ke Roastery pertama
        session(['active_cabang_id' => $outlet->id]);
        $this->assertSame($r1->id, RoastingBatchService::roasteryCabangId());
    }

    public function test_beli_buah_kopi_tersimpan_di_roastery_tanpa_kode_rst001_dan_ditolak_tanpa_roastery(): void
    {
        Cabang::where('tipe', 'roastery')->update(['is_active' => false]);
        $owner = $this->buatUser('owner');
        $petani = Petani::create(['kode_petani' => 'PTD' . rand(1000, 9999), 'nama' => 'Petani TD', 'is_active' => true]);
        $cherry = Item::where('kode_item', 'like', 'BHK-%')->firstOrFail();
        $data = ['tanggal' => now()->toDateString(), 'petani_id' => $petani->id, 'cherry_item_id' => $cherry->id,
            'jenis_buah' => 'arabika', 'qty_kg' => 10, 'harga_per_kg' => 12000, 'kualitas_grade' => 'A'];

        $this->actingAs($owner)->post(route('beli-cherry.store'), $data)
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Belum ada lokasi Roastery'));
        $this->actingAs($owner)->get(route('beli-cherry.index'))->assertOk()->assertSee('Belum ada lokasi');

        $roastery = $this->cabang('roastery', 'ROAST' . rand(10, 99));
        $this->actingAs($owner)->post(route('beli-cherry.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame($roastery->id, CherryPurchase::latest('id')->first()->cabang_id);
        $this->actingAs($owner)->get(route('beli-cherry.index'))->assertOk()->assertSee($roastery->nama_cabang);
    }

    // ===================== Saklar QR =====================

    public function test_saklar_pesan_lewat_qr_di_edit_cabang(): void
    {
        $owner = $this->buatUser('owner');
        $outlet = $this->cabang('cabang', 'QRT' . rand(100, 999));
        $meja = Meja::create(['cabang_id' => $outlet->id, 'nomor_meja' => 7, 'nama_meja' => 'Meja 7', 'kapasitas' => 4,
            'qr_token' => Meja::generateQrToken(), 'qr_type' => 'permanent', 'status' => 'aktif']);
        $url = route('order.menu', [$outlet->kode_cabang, 7]) . '?token=' . $meja->qr_token;

        $this->get($url)->assertForbidden();
        $this->actingAs($owner)->get(route('cabang.edit', $outlet))->assertOk()->assertSee('Aktifkan pesan lewat QR di meja');

        $payload = ['nama_cabang' => $outlet->nama_cabang, 'kode_cabang' => $outlet->kode_cabang, 'tipe' => 'cabang',
            'is_active' => 1, 'kepala_cabang_id' => '', 'qr_ordering_active' => '1'];
        $this->actingAs($owner)->put(route('cabang.update', $outlet), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue((bool) $outlet->fresh()->qr_ordering_active);
        auth()->logout();
        $this->get($url)->assertOk();

        $this->actingAs($owner)->put(route('cabang.update', $outlet), ['qr_ordering_active' => '0'] + $payload)->assertSessionHasNoErrors();
        $this->assertFalse((bool) $outlet->fresh()->qr_ordering_active);
    }
}
