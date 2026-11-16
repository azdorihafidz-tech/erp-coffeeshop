<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Karyawan;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\PengaturanGaji;
use App\Models\RecurringTransaksi;
use App\Models\Stock;
use App\Models\TransaksiKeuangan;
use App\Services\CascadeDeleteService;
use App\Services\LabaRugiFormalService;
use App\Services\NeracaService;
use App\Services\PenggajianService;
use App\Services\StokService;
use App\Support\TransaksiInternal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Tahap P porting dimsum 4.51 + temuan coffeeshop:
 * 8  Bayar gaji mengurangi kas (+ Batal Bayar)     9  Generate ulang tidak menimpa slip disetujui/dibayar
 * 10 Potongan telat per menit dipakai              11 Transaksi Berulang: frekuensi, tidak dobel
 * 12 Cabang beroperasi tidak bisa dihapus          13 Neraca tidak menghitung stok barang terhapus
 * 14 Laporan kas tanpa transfer internal + filter cabang
 * 15 Penyesuaian Stok salah hitung/audit turun → Selisih Persediaan (6-1406)
 * 16 Kategori punya akun COA → beban masuk Laba Rugi Formal; transfer internal tidak
 */
class PerbaikanP3Test extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private Cabang $c;

    protected function setUp(): void
    {
        parent::setUp();
        TransaksiInternal::reset();
        $this->c = Cabang::create(['nama_cabang' => 'Outlet P3 ' . uniqid(), 'kode_cabang' => 'P3' . rand(10000, 99999), 'tipe' => 'cabang', 'is_active' => true]);
    }

    private function kasTunai(Cabang $cabang, float $saldo = 1000000): Kas
    {
        return Kas::firstOrCreate(['cabang_id' => $cabang->id, 'default_untuk' => 'tunai'], ['nama_kas' => 'Kas tunai P3 ' . $cabang->id,
            'tipe_kas' => 'tunai', 'saldo_awal' => 0, 'saldo_sekarang' => $saldo, 'is_active' => true]);
    }

    private function karyawan(float $gaji = 3000000): Karyawan
    {
        return Karyawan::create([
            'cabang_id' => $this->c->id, 'nik' => 'KP3' . rand(10000, 99999), 'nama_lengkap' => 'Karyawan P3 ' . rand(100, 999),
            'jenis_kelamin' => 'L', 'jabatan' => 'Staf', 'tipe_karyawan' => 'tetap', 'tanggal_masuk' => '2026-01-01',
            'gaji_pokok' => $gaji, 'status' => 'aktif',
        ]);
    }

    // ---------------- 8. Bayar gaji → kas ----------------

    public function test_bayar_gaji_mengurangi_kas_dan_batal_bayar_mengembalikan(): void
    {
        $kas = $this->kasTunai($this->c, 5000000);
        $slip = app(PenggajianService::class)->generate($this->karyawan(), '2026-10');
        $total = (float) $slip->total_gaji;
        $this->assertGreaterThan(0, $total);
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->get(route('penggajian.show', $slip))->assertOk()->assertSee('modalBayarGaji', false)->assertSee($kas->nama_kas);

        $this->actingAs($owner)->post(route('penggajian.bayar', $slip))->assertSessionHasErrors('kas_id');
        $this->assertSame('draft', $slip->fresh()->status);

        $this->actingAs($owner)->post(route('penggajian.bayar', $slip), ['kas_id' => $kas->id, 'tanggal_bayar' => today()->toDateString()])
            ->assertSessionHas('success');
        $slip->refresh();
        $this->assertSame('dibayar', $slip->status);
        $this->assertEqualsWithDelta(5000000 - $total, (float) $kas->fresh()->saldo_sekarang, 0.01);
        $trx = TransaksiKeuangan::find($slip->transaksi_keuangan_id);
        $this->assertSame('penggajian', $trx->referensi_type);
        $this->assertSame('GAJI', KategoriTransaksi::find($trx->kategori_id)?->kode);
        $this->assertEqualsWithDelta($total, (float) $trx->jumlah, 0.01);

        // transaksi gaji tidak bisa dihapus dari Kas & Transaksi
        $this->actingAs($owner)->delete(route('keuangan.destroy', $trx))->assertSessionHas('error');

        $this->actingAs($owner)->post(route('penggajian.batal-bayar', $slip))->assertSessionHas('success');
        $this->assertSame('draft', $slip->fresh()->status);
        $this->assertEqualsWithDelta(5000000, (float) $kas->fresh()->saldo_sekarang, 0.01);
        $this->assertFalse(TransaksiKeuangan::withTrashed()->whereKey($trx->id)->exists());
    }

    public function test_bayar_gaji_menolak_kas_cabang_lain(): void
    {
        $lain = Cabang::create(['nama_cabang' => 'Outlet P3 Lain ' . uniqid(), 'kode_cabang' => 'P3L' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        $kasLain = $this->kasTunai($lain);
        $slip = app(PenggajianService::class)->generate($this->karyawan(), '2026-10');
        $this->actingAs($this->buatUser('owner'))->post(route('penggajian.bayar', $slip), ['kas_id' => $kasLain->id])->assertSessionHas('error');
        $this->assertSame('draft', $slip->fresh()->status);
    }

    // ---------------- 9. Generate ulang ----------------

    public function test_generate_ulang_tidak_menimpa_slip_disetujui(): void
    {
        $k = $this->karyawan(3000000);
        $svc = app(PenggajianService::class);
        $slip = $svc->generate($k, '2026-09');
        $slip->update(['status' => 'disetujui']);
        $totalLama = (float) $slip->total_gaji;
        $k->update(['gaji_pokok' => 9000000]);

        try {
            $svc->generate($k, '2026-09');
            $this->fail('Seharusnya ditolak');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak bisa dihitung ulang', $e->getMessage());
        }
        $hasil = $svc->bulkGenerate($this->c->id, '2026-09');
        $this->assertContains($k->nama_lengkap, $hasil['dilewati']);
        $this->assertSame('disetujui', $slip->fresh()->status);
        $this->assertEqualsWithDelta($totalLama, (float) $slip->fresh()->total_gaji, 0.01);
    }

    // ---------------- 10. Potongan telat ----------------

    public function test_potongan_telat_per_menit_dipakai(): void
    {
        $k = $this->karyawan();
        PengaturanGaji::getSetting()->update(['potongan_telat_per_menit' => 1000]);
        \Illuminate\Support\Facades\Cache::flush();
        foreach (['2026-08-03' => 10, '2026-08-04' => 25] as $tgl => $menit) {
            Absensi::create(['karyawan_id' => $k->id, 'cabang_id' => $this->c->id, 'tanggal' => $tgl, 'status' => 'hadir', 'is_telat' => true, 'menit_telat' => $menit]);
        }
        $slip = app(PenggajianService::class)->generate($k, '2026-08');
        $this->assertSame(35, (int) $slip->menit_telat_total);
        $this->assertEqualsWithDelta(35000, (float) $slip->potongan_telat, 0.01);
        $this->actingAs($this->buatUser('owner'))->get(route('penggajian.show', $slip))->assertOk()->assertSee('Potongan Telat (35 menit)');
    }

    // ---------------- 11. Transaksi Berulang ----------------

    private function template(array $attr): RecurringTransaksi
    {
        return RecurringTransaksi::create(array_merge([
            'nama_template' => 'Sewa P3', 'cabang_id' => $this->c->id, 'kas_id' => $this->kasTunai($this->c)->id,
            'kategori_id' => KategoriTransaksi::where('kode', 'SEWA')->value('id'), 'tipe' => 'pengeluaran', 'jumlah' => 100000,
            'keterangan' => 'Sewa P3', 'frekuensi' => 'bulanan', 'tanggal_jatuh_tempo' => 31, 'tanggal_mulai' => '2026-01-01', 'is_active' => true,
        ], $attr));
    }

    public function test_transaksi_berulang_tidak_dobel_walau_dijalankan_berkali_kali(): void
    {
        $t = $this->template([]);
        $hari = Carbon::parse('2026-02-28'); // bulan pendek: tgl 31 → 28 Feb
        RecurringTransaksi::jalankanYangJatuhTempo($hari);
        RecurringTransaksi::jalankanYangJatuhTempo($hari);
        RecurringTransaksi::jalankanYangJatuhTempo($hari->copy()->addDays(3)); // masih periode Feb
        $this->assertSame(1, TransaksiKeuangan::where('cabang_id', $this->c->id)->where('keterangan', 'Sewa P3 (Recurring)')->count());
        $this->assertSame('2026-02-28', $t->fresh()->tanggal_terakhir_generate->toDateString());
        Carbon::setTestNow('2026-03-05');
        $this->actingAs($this->buatUser('owner'))->post(route('recurring.generate', $t->fresh()))->assertSessionHas('error');
        Carbon::setTestNow();
    }

    public function test_frekuensi_harian_mingguan_tahunan_dihormati(): void
    {
        $harian = $this->template(['frekuensi' => 'harian', 'tanggal_mulai' => '2026-03-01']);
        $this->assertSame('2026-03-10', $harian->jatuhTempoTerakhir(Carbon::parse('2026-03-10'))->toDateString());
        $mingguan = $this->template(['frekuensi' => 'mingguan', 'tanggal_mulai' => '2026-03-02']); // Senin
        $this->assertSame('2026-03-09', $mingguan->jatuhTempoTerakhir(Carbon::parse('2026-03-14'))->toDateString());
        $tahunan = $this->template(['frekuensi' => 'tahunan', 'tanggal_jatuh_tempo' => 15, 'tanggal_mulai' => '2025-07-01']);
        $this->assertSame('2025-07-15', $tahunan->jatuhTempoTerakhir(Carbon::parse('2026-07-14'))->toDateString());
        $this->assertNull((clone $tahunan)->forceFill(['tanggal_terakhir_generate' => '2025-07-15'])->perluDibuat(Carbon::parse('2026-07-14')));
        $this->actingAs($this->buatUser('owner'))->get(route('recurring.index'))->assertOk();
    }

    // ---------------- 12. Hapus cabang ----------------

    public function test_cabang_yang_punya_transaksi_tidak_bisa_dihapus(): void
    {
        $this->assertSame([], app(CascadeDeleteService::class)->alasanCabangTidakBolehDihapus($this->c));
        TransaksiKeuangan::create(['cabang_id' => $this->c->id, 'nomor_transaksi' => 'P3-' . uniqid(), 'tanggal_transaksi' => today(),
            'tipe' => 'pengeluaran', 'kategori' => 'lainnya', 'keterangan' => 'x', 'jumlah' => 1]);
        $this->assertNotEmpty(app(CascadeDeleteService::class)->alasanCabangTidakBolehDihapus($this->c));
        $this->actingAs($this->buatUser('owner'))->delete(route('cabang.destroy', $this->c))->assertSessionHas('error');
        $this->assertNull($this->c->fresh()->deleted_at);
    }

    // ---------------- 13. Neraca ----------------

    public function test_neraca_tidak_menghitung_stok_barang_yang_dihapus(): void
    {
        $item = Item::create(['kode_item' => 'P3-' . strtoupper(substr(uniqid(), -8)), 'nama_item' => 'Bahan P3', 'tipe' => 'bahan_baku', 'satuan' => 'pcs',
            'harga_beli_terakhir' => 0, 'is_active' => true, 'track_stok' => true]);
        $nilai = fn () => app(NeracaService::class)->hitungNeraca(today(), $this->c->id)['aset']['lancar']['persediaan_bahan_baku'];
        $sebelum = $nilai();
        app(StokService::class)->masuk($item->id, $this->c->id, 10, 'stok P3', 'test', null, 5000);
        $this->assertEqualsWithDelta($sebelum + 50000, $nilai(), 0.01);
        DB::table('items')->where('id', $item->id)->update(['deleted_at' => now()]); // cara hapus lama (stok ikut tersembunyi)
        $this->assertEqualsWithDelta($sebelum, $nilai(), 0.01);
    }

    // ---------------- 14. Laporan kas ----------------

    private function trx(string $kode, string $tipe, float $jumlah, string $tanggal = '2026-06-10', ?int $cabangId = null): TransaksiKeuangan
    {
        return TransaksiKeuangan::create([
            'cabang_id' => $cabangId ?? $this->c->id, 'nomor_transaksi' => 'P3-' . uniqid(), 'tanggal_transaksi' => $tanggal,
            'tipe' => $tipe, 'kategori' => 'lainnya', 'kategori_id' => KategoriTransaksi::where('kode', $kode)->value('id'),
            'keterangan' => 'P3 ' . $kode, 'jumlah' => $jumlah,
        ]);
    }

    public function test_laporan_keuangan_tidak_menghitung_transfer_internal_dan_saldo_awal(): void
    {
        $this->trx('MUTASI-OUT', 'pengeluaran', 700000);
        $this->trx('MUTASI-IN', 'pemasukan', 700000);
        $this->trx('SALDO', 'pemasukan', 5000000);
        $this->trx('SEWA', 'pengeluaran', 150000);

        $q = TransaksiKeuangan::where('cabang_id', $this->c->id)->whereDate('tanggal_transaksi', '2026-06-10')->where('keterangan', 'like', 'P3 %');
        $this->assertEqualsWithDelta(150000, (float) TransaksiInternal::kecualikan(clone $q)->sum('jumlah'), 0.01);
        $this->assertEqualsWithDelta(6400000, (float) TransaksiInternal::hanya(clone $q)->sum('jumlah'), 0.01);

        $html = $this->actingAs($this->buatUser('owner'))
            ->get(route('laporan.keuangan.laba-rugi', ['dari' => '2026-06-10', 'sampai' => '2026-06-10', 'cabang_id' => $this->c->id]))
            ->assertOk()->assertSee('Tidak dihitung (uang hanya pindah tempat)')->getContent();
        $this->assertStringContainsString('5.700.000', $html);
        $this->actingAs($this->buatUser('owner'))->get(route('keuangan.laporan'))->assertOk()->assertSee('Pemasukan &amp; Pengeluaran Usaha', false);
    }

    public function test_laporan_keuangan_role_cabang_tidak_bisa_membuka_cabang_lain_lewat_alamat(): void
    {
        $lain = Cabang::create(['nama_cabang' => 'Outlet P3 Rahasia ' . uniqid(), 'kode_cabang' => 'P3R' . rand(1000, 9999), 'tipe' => 'cabang', 'is_active' => true]);
        TransaksiKeuangan::create(['cabang_id' => $lain->id, 'nomor_transaksi' => 'P3-' . uniqid(), 'tanggal_transaksi' => '2026-06-11',
            'tipe' => 'pengeluaran', 'kategori' => 'lainnya', 'keterangan' => 'RAHASIA-CABANG-LAIN-P3', 'jumlah' => 123457]);
        $manajer = $this->buatUser('manajer_cabang', $this->c->id);
        DB::table('role_permissions')->insertOrIgnore(['role' => 'manajer_cabang', 'permission_id' => DB::table('permissions')->where('name', 'laporan.view')->value('id')]);
        \Illuminate\Support\Facades\Cache::forget('role_permissions_manajer_cabang');

        $this->actingAs($manajer)->get(route('laporan.keuangan.arus-kas', ['dari' => '2026-06-11', 'sampai' => '2026-06-11', 'cabang_id' => $lain->id]))
            ->assertOk()->assertDontSee('RAHASIA-CABANG-LAIN-P3');
    }

    // ---------------- 15. Penyesuaian Stok selisih ----------------

    public function test_penyesuaian_salah_hitung_yang_menurunkan_stok_tercatat_selisih_persediaan(): void
    {
        $item = Item::create(['kode_item' => 'P3S-' . strtoupper(substr(uniqid(), -8)), 'nama_item' => 'Susu P3', 'tipe' => 'bahan_baku', 'satuan' => 'liter',
            'harga_beli_terakhir' => 0, 'is_active' => true, 'track_stok' => true]);
        $stok = app(StokService::class);
        $stok->masuk($item->id, $this->c->id, 10, 'stok P3', 'test', null, 20000);

        $stok->adjustment($item->id, $this->c->id, 8, 'hitung ulang', null, 'salah_hitung');
        $tk = TransaksiKeuangan::where('cabang_id', $this->c->id)->where('keterangan', 'like', 'Adj. stok Susu P3:%')->latest('id')->first();
        $this->assertNotNull($tk, 'salah hitung yang menurunkan stok tercatat');
        $this->assertSame('OPN-SELISIH', KategoriTransaksi::find($tk->kategori_id)->kode);
        $this->assertSame('6-1406', KategoriTransaksi::find($tk->kategori_id)->kode_akun_coa);
        $this->assertEqualsWithDelta(40000, (float) $tk->jumlah, 0.01);
        $this->assertNull($tk->kas_id, 'beban tanpa kas');

        // salah hitung yang MENAIKKAN stok tetap tidak dicatat
        $jumlah = TransaksiKeuangan::where('cabang_id', $this->c->id)->count();
        $stok->adjustment($item->id, $this->c->id, 9, 'ketemu', null, 'audit');
        $this->assertSame($jumlah, TransaksiKeuangan::where('cabang_id', $this->c->id)->count());
        $this->assertEquals(9, $stok->getStok($item->id, $this->c->id));
    }

    // ---------------- 16. Akun COA kategori → Laba Rugi Formal ----------------

    public function test_beban_kas_dan_transaksi_masuk_laba_rugi_formal_transfer_internal_tidak(): void
    {
        // DB tes sudah menjalankan KategoriCoaBackfillSeeder (sama dengan Update Database)
        $this->assertSame('6-1101', KategoriTransaksi::where('kode', 'GAJI')->value('kode_akun_coa'));
        $this->assertNull(KategoriTransaksi::where('kode', 'MUTASI-OUT')->value('kode_akun_coa'));
        $this->assertNull(KategoriTransaksi::where('kode', 'SETORKSR-OUT')->value('kode_akun_coa'));
        $this->assertNull(KategoriTransaksi::where('kode', 'RETUR-BELI')->value('kode_akun_coa'));

        $this->trx('SEWA', 'pengeluaran', 1500000, '2026-05-10');
        $this->trx('MUTASI-OUT', 'pengeluaran', 999000, '2026-05-10');
        $this->trx('SETORKSR-OUT', 'pengeluaran', 888000, '2026-05-10');

        $lr = app(LabaRugiFormalService::class)->hitungLabaRugi(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $this->c->id);
        $this->assertEqualsWithDelta(1500000, $lr['beban_operasional']['total'], 0.01, 'hanya sewa; transfer internal tidak dihitung');
        $this->assertEqualsWithDelta(0, $lr['pendapatan_lain']['total'] + $lr['beban_lain']['total'], 0.01);

        // Seeder idempotent & tidak menimpa reklas manual Owner
        KategoriTransaksi::where('kode', 'SEWA')->update(['kode_akun_coa' => '6-1103']);
        $this->artisan('db:seed', ['--class' => 'KategoriCoaBackfillSeeder'])->assertSuccessful();
        $this->assertSame('6-1103', KategoriTransaksi::where('kode', 'SEWA')->value('kode_akun_coa'));
    }
}
