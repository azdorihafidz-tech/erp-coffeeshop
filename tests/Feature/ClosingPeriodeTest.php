<?php

namespace Tests\Feature;

use App\Exceptions\PeriodeTerkunciException;
use App\Models\Cabang;
use App\Models\ClosingPeriode;
use App\Models\Item;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\TransaksiKeuangan;
use App\Services\ClosingLockService;
use App\Services\ClosingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Closing Periode (2026-10-17) — snapshot closing, kunci periode,
 * hak akses, buka kunci & versi. Semua data test memakai cabang baru dan
 * tanggal Januari 2025 supaya tidak tercampur data seeder.
 */
class ClosingPeriodeTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private ClosingService $svc;
    private Cabang $cabang;
    private Kas $kasTunai;
    private Kas $kasBank;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ClosingService::class);
        $this->cabang = $this->bikinCabang('CLT');
        $this->kasTunai = Kas::create(['cabang_id' => $this->cabang->id, 'nama_kas' => 'Kas Tunai CLT', 'tipe_kas' => 'tunai',
            'saldo_awal' => 1000000, 'saldo_sekarang' => 1000000, 'is_active' => true]);
        $this->kasBank = Kas::create(['cabang_id' => $this->cabang->id, 'nama_kas' => 'Kas Bank CLT', 'tipe_kas' => 'bank',
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);
    }

    // ------------------------------------------------------------------ helper

    private function bikinCabang(string $prefix): Cabang
    {
        return Cabang::create([
            'nama_cabang' => 'Cabang Test ' . $prefix . uniqid(),
            'kode_cabang' => substr($prefix . strtoupper(substr(uniqid(), -6)), 0, 10),
            'tipe' => 'cabang',
            'is_active' => true,
        ]);
    }

    private function order(string $tanggal, float $total, float $hpp, string $status = 'selesai', ?int $cabangId = null, string $customerType = 'retail'): Order
    {
        $o = Order::create([
            'cabang_id' => $cabangId ?? $this->cabang->id,
            'nomor_order' => 'TST-CL-' . uniqid() . ++$this->seq,
            'tanggal_order' => $tanggal,
            'tipe_order' => 'produk_jadi',
            'tipe_transaksi' => 'takeaway',
            'customer_type' => $customerType,
            'total_harga' => $total,
            'diskon' => 0,
            'total_bayar' => $total,
            'jumlah_bayar' => $total,
            'tipe_pembayaran' => 'tunai',
            'status' => $status,
        ]);
        OrderItem::create([
            'order_id' => $o->id, 'nama_item' => 'Kopi Test', 'qty' => 1, 'satuan' => 'gelas',
            'harga_satuan' => $total, 'total_harga' => $total, 'hpp' => $hpp,
        ]);

        return $o;
    }

    private function trx(string $tanggal, string $tipe, float $jumlah, array $extra = []): TransaksiKeuangan
    {
        return TransaksiKeuangan::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'kas_id' => $this->kasTunai->id,
            'nomor_transaksi' => 'TST-CL-' . uniqid() . ++$this->seq,
            'tanggal_transaksi' => $tanggal,
            'tipe' => $tipe,
            'kategori' => 'lainnya',
            'keterangan' => 'test closing',
            'jumlah' => $jumlah,
        ], $extra));
    }

    private function kategori(string $kode, string $tipe): KategoriTransaksi
    {
        return KategoriTransaksi::firstOrCreate(['kode' => $kode], ['nama' => $kode, 'tipe' => $tipe, 'is_system' => true, 'is_active' => true, 'urutan' => 99]);
    }

    private function closingFinal(?int $cabangId, string $mulai, string $selesai, string $jenis = 'custom'): ClosingPeriode
    {
        $owner = $this->buatUser('owner');

        return $this->svc->simpan($jenis, $cabangId, Carbon::parse($mulai), Carbon::parse($selesai), $owner, true);
    }

    // ---------------------------------------------------------- perhitungan

    public function test_omzet_hpp_abaikan_pending_dan_dibatalkan(): void
    {
        $this->order('2025-01-10', 100000, 40000);
        $this->order('2025-01-12', 50000, 20000);
        $this->order('2025-01-12', 999000, 1, 'pending');
        $this->order('2025-01-12', 777000, 1, 'dibatalkan');
        $this->order('2025-02-01', 888000, 1); // di luar periode

        $d = $this->svc->hitung($this->cabang->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));

        $this->assertSame(2, $d['penjualan']['jumlah_transaksi']);
        $this->assertEqualsWithDelta(150000, $d['laba_rugi']['omzet'], 0.01);
        $this->assertEqualsWithDelta(60000, $d['laba_rugi']['hpp'], 0.01);
        $this->assertEqualsWithDelta(90000, $d['laba_rugi']['laba_kotor'], 0.01);
        $this->assertSame(1, $d['penjualan']['pending']['jumlah']);
        $this->assertSame(1, $d['penjualan']['dibatalkan']['jumlah']);
        $this->assertNotEmpty(collect($d['peringatan'])->filter(fn ($w) => str_contains($w, 'bill tersimpan')));
    }

    public function test_klasifikasi_pemasukan_beban_persediaan_dan_mutasi(): void
    {
        $ops = $this->kategori('OPS', 'pengeluaran');
        $pbb = $this->kategori('PBB', 'pengeluaran');
        $lnyIn = $this->kategori('LNY-IN', 'pemasukan');
        $mutOut = $this->kategori('MUTASI-OUT', 'pengeluaran');
        $mutIn = $this->kategori('MUTASI-IN', 'pemasukan');

        $this->order('2025-01-10', 100000, 30000);
        // Cerminan kas penjualan — tidak boleh dihitung lagi sbg pemasukan lain
        $this->trx('2025-01-10', 'pemasukan', 100000, ['kategori' => 'penjualan', 'referensi_type' => 'order']);
        $this->trx('2025-01-11', 'pengeluaran', 25000, ['kategori_id' => $ops->id]);
        $this->trx('2025-01-11', 'pengeluaran', 70000, ['kategori_id' => $pbb->id, 'kategori' => 'pembelian_bahan']);
        $this->trx('2025-01-12', 'pemasukan', 5000, ['kategori_id' => $lnyIn->id]);
        $this->trx('2025-01-13', 'pengeluaran', 200000, ['kategori_id' => $mutOut->id, 'referensi_type' => 'transfer_antar_kas']);
        $this->trx('2025-01-13', 'pemasukan', 200000, ['kategori_id' => $mutIn->id, 'kas_id' => $this->kasBank->id, 'referensi_type' => 'transfer_antar_kas']);

        $d = $this->svc->hitung($this->cabang->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));

        $this->assertEqualsWithDelta(5000, $d['laba_rugi']['pemasukan_lain'], 0.01);
        $this->assertEqualsWithDelta(25000, $d['laba_rugi']['beban'], 0.01);
        $this->assertEqualsWithDelta(70000, $d['keuangan']['total_persediaan_aset'], 0.01);
        // laba bersih = 100k - 30k + 5k - 25k (PBB & mutasi tidak masuk)
        $this->assertEqualsWithDelta(50000, $d['laba_rugi']['laba_bersih'], 0.01);
        $this->assertEqualsWithDelta(200000, $d['keuangan']['mutasi']['transfer_antar_kas'], 0.01);
        $this->assertSame(1, $d['keuangan']['mutasi']['transfer_antar_kas_jumlah']);

        // Kas: tunai 1jt + 100k + 5k - 25k - 70k - 200k = 810k; bank 200k
        $tunai = collect($d['kas']['detail'])->firstWhere('kas_id', $this->kasTunai->id);
        $bank = collect($d['kas']['detail'])->firstWhere('kas_id', $this->kasBank->id);
        $this->assertEqualsWithDelta(1000000, $tunai['saldo_awal'], 0.01);
        $this->assertEqualsWithDelta(810000, $tunai['saldo_akhir'], 0.01);
        $this->assertEqualsWithDelta(200000, $bank['saldo_akhir'], 0.01);
        $this->assertEqualsWithDelta(810000, $d['kas']['total_tunai'], 0.01);
        $this->assertEqualsWithDelta(200000, $d['kas']['total_non_tunai'], 0.01);
    }

    public function test_saldo_awal_periode_ikut_transaksi_sebelumnya(): void
    {
        $this->trx('2024-12-20', 'pemasukan', 300000);
        $this->trx('2025-01-05', 'pengeluaran', 50000);

        $d = $this->svc->hitung($this->cabang->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));
        $tunai = collect($d['kas']['detail'])->firstWhere('kas_id', $this->kasTunai->id);

        $this->assertEqualsWithDelta(1300000, $tunai['saldo_awal'], 0.01);
        $this->assertEqualsWithDelta(50000, $tunai['keluar'], 0.01);
        $this->assertEqualsWithDelta(1250000, $tunai['saldo_akhir'], 0.01);
    }

    public function test_setoran_menunggu_jadi_dalam_perjalanan_dan_tidak_menambah_kas_tujuan(): void
    {
        $out = $this->kategori('SETOR-OUT', 'pengeluaran');
        $in = $this->kategori('SETOR-IN', 'pemasukan');
        $tujuan = $this->bikinCabang('CLP');
        $kasPusat = Kas::create(['cabang_id' => $tujuan->id, 'nama_kas' => 'Kas Pusat', 'tipe_kas' => 'bank', 'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true]);

        $this->trx('2025-01-15', 'pengeluaran', 400000, ['kategori_id' => $out->id, 'status_setoran' => 'menunggu_diterima']);
        $this->trx('2025-01-15', 'pemasukan', 400000, ['kategori_id' => $in->id, 'cabang_id' => $tujuan->id,
            'kas_id' => $kasPusat->id, 'status_setoran' => 'menunggu_diterima']);

        $d = $this->svc->hitung($this->cabang->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));
        $this->assertEqualsWithDelta(400000, $d['keuangan']['dalam_perjalanan'], 0.01);
        $this->assertEqualsWithDelta(0, $d['laba_rugi']['beban'], 0.01);

        $dPusat = $this->svc->hitung($tujuan->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));
        $this->assertEqualsWithDelta(0, $dPusat['kas']['total_akhir'], 0.01);
        $this->assertEqualsWithDelta(400000, $dPusat['keuangan']['mutasi']['setoran_masuk_menunggu'], 0.01);
    }

    public function test_konsolidasi_eliminasi_penjualan_internal(): void
    {
        $this->order('2025-01-10', 300000, 200000, 'selesai', null, 'internal');
        $this->order('2025-01-10', 100000, 40000);

        $per = $this->svc->hitung($this->cabang->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));
        $this->assertEqualsWithDelta(400000, $per['laba_rugi']['omzet'], 0.01);
        $this->assertEqualsWithDelta(0, $per['laba_rugi']['eliminasi_omzet'], 0.01);

        $kons = $this->svc->hitung(null, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));
        $this->assertTrue($kons['meta']['konsolidasi']);
        $this->assertEqualsWithDelta(300000, $kons['laba_rugi']['eliminasi_omzet'], 0.01);
        $this->assertEqualsWithDelta(200000, $kons['laba_rugi']['eliminasi_hpp'], 0.01);
        $this->assertEqualsWithDelta($kons['laba_rugi']['omzet_bersih'] - 300000, $kons['laba_rugi']['omzet'], 0.01);
    }

    public function test_stok_akhir_periode_dikoreksi_pergerakan_setelahnya(): void
    {
        $item = Item::where('tipe', 'bahan_baku')->firstOrFail();
        Stock::create(['item_id' => $item->id, 'lokasi_id' => $this->cabang->id, 'qty' => 10, 'qty_minimum' => 0]);
        // Setelah periode: masuk 4, keluar 1 → stok akhir Jan = 10 - 4 + 1 = 7
        StockMovement::create(['item_id' => $item->id, 'lokasi_tujuan_id' => $this->cabang->id, 'qty' => 4, 'tipe' => 'masuk', 'created_at' => '2025-02-03 10:00:00']);
        StockMovement::create(['item_id' => $item->id, 'lokasi_asal_id' => $this->cabang->id, 'qty' => 1, 'tipe' => 'keluar', 'created_at' => '2025-02-04 10:00:00']);

        $d = $this->svc->hitung($this->cabang->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-01-31'));
        $row = collect($d['stok']['detail'])->firstWhere('kode', $item->kode_item);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(7, $row['qty'], 0.001);
    }

    // ------------------------------------------------------- kunci periode

    public function test_final_mengunci_create_update_delete_di_periode(): void
    {
        $trx = $this->trx('2025-01-10', 'pengeluaran', 10000);
        $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31');

        try {
            $this->trx('2025-01-20', 'pengeluaran', 5000);
            $this->fail('Create di periode terkunci harus ditolak');
        } catch (PeriodeTerkunciException $e) {
            $this->assertStringContainsString('sudah di-closing', $e->getMessage());
        }

        $this->expectException(PeriodeTerkunciException::class);
        $trx->update(['jumlah' => 99999]);
    }

    public function test_delete_dan_order_di_periode_terkunci_ditolak(): void
    {
        $trx = $this->trx('2025-01-10', 'pengeluaran', 10000);
        $order = $this->order('2025-01-10', 50000, 1000);
        $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31');

        $this->assertThrows(fn () => $trx->delete(), PeriodeTerkunciException::class);
        $this->assertThrows(fn () => $order->update(['status' => 'dibatalkan']), PeriodeTerkunciException::class);
        $this->assertThrows(fn () => $this->order('2025-01-15', 1000, 0), PeriodeTerkunciException::class);
        $this->assertDatabaseHas('transaksi_keuangans', ['id' => $trx->id, 'deleted_at' => null]);
    }

    public function test_kolom_bebas_tetap_boleh_diubah(): void
    {
        $out = $this->kategori('SETOR-OUT', 'pengeluaran');
        $trx = $this->trx('2025-01-10', 'pengeluaran', 10000, ['kategori_id' => $out->id, 'status_setoran' => 'menunggu_diterima']);
        $order = $this->order('2025-01-10', 50000, 1000);
        $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31');

        $trx->update(['status_setoran' => 'diterima', 'waktu_diterima' => now()]);
        $order->update(['catatan_produksi' => 'diambil pelanggan']);

        $this->assertSame('diterima', $trx->fresh()->status_setoran->value);
        $this->assertSame('diambil pelanggan', $order->fresh()->catatan_produksi);
    }

    public function test_di_luar_periode_dan_cabang_lain_tidak_terkunci(): void
    {
        $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31');
        $lain = $this->bikinCabang('CLL');

        $a = $this->trx('2025-02-01', 'pengeluaran', 1000);
        $b = $this->trx('2025-01-15', 'pengeluaran', 1000, ['cabang_id' => $lain->id, 'kas_id' => null]);

        $this->assertNotNull($a->id);
        $this->assertNotNull($b->id);
    }

    public function test_konsolidasi_final_mengunci_semua_cabang(): void
    {
        $this->closingFinal(null, '2025-01-01', '2025-01-31');
        $lain = $this->bikinCabang('CLK');

        $this->assertThrows(
            fn () => $this->trx('2025-01-15', 'pengeluaran', 1000, ['cabang_id' => $lain->id, 'kas_id' => null]),
            PeriodeTerkunciException::class
        );
    }

    public function test_hapus_transaksi_via_http_ditolak_dengan_pesan(): void
    {
        $trx = $this->trx('2025-01-10', 'pengeluaran', 10000);
        $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31');
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)
            ->from(route('keuangan.index'))
            ->delete(route('keuangan.destroy', $trx))
            ->assertRedirect(route('keuangan.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah di-closing'));

        $this->assertDatabaseHas('transaksi_keuangans', ['id' => $trx->id, 'deleted_at' => null]);
    }

    // ------------------------------------------- simpan / final / unlock

    public function test_buka_kunci_lalu_closing_ulang_jadi_versi_2(): void
    {
        $trx = $this->trx('2025-01-10', 'pengeluaran', 10000);
        $c1 = $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31', 'bulanan');
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->post(route('closing.unlock', $c1), ['alasan_buka' => 'Koreksi pengeluaran'])
            ->assertRedirect(route('closing.show', $c1));
        $this->assertSame('dibuka', $c1->fresh()->status);

        // Setelah dibuka, koreksi boleh
        $trx->update(['jumlah' => 15000]);

        $c2 = $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31', 'bulanan');
        $this->assertSame(2, $c2->versi);
        $this->assertSame($c1->id, $c2->parent_id);
        // Arsip lama tetap angka lama
        $this->assertEqualsWithDelta(10000, $c1->fresh()->data['laba_rugi']['beban'], 0.01);
        $this->assertEqualsWithDelta(15000, $c2->data['laba_rugi']['beban'], 0.01);
    }

    public function test_jenis_sama_bertumpuk_ditolak_jenis_beda_boleh(): void
    {
        $this->closingFinal($this->cabang->id, '2025-01-10', '2025-01-10', 'harian');
        $bulanan = $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31', 'bulanan');
        $this->assertSame('final', $bulanan->status);

        $this->expectException(\InvalidArgumentException::class);
        $this->closingFinal($this->cabang->id, '2025-01-15', '2025-02-10', 'bulanan');
    }

    public function test_tanggal_selesai_melewati_hari_ini_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->validasiPeriode('harian', $this->cabang->id, today(), today()->addDay());
    }

    public function test_snapshot_tidak_berubah_walau_data_berubah_setelah_final(): void
    {
        $this->order('2025-01-10', 100000, 40000);
        $c = $this->closingFinal($this->cabang->id, '2025-01-01', '2025-01-31');

        // Data lain cabang di luar kunci (simulasi perubahan setelah unlock)
        app(ClosingLockService::class)->flush();
        $c->update(['status' => 'dibuka']);
        app(ClosingLockService::class)->flush();
        $this->order('2025-01-11', 500000, 1);

        $this->assertEqualsWithDelta(100000, $c->fresh()->data['laba_rugi']['omzet'], 0.01);
        $this->assertEqualsWithDelta(100000, (float) $c->fresh()->total_omzet, 0.01);
    }

    // ------------------------------------------------------- HTTP & akses

    public function test_manajer_hanya_draft_admin_pusat_bisa_final(): void
    {
        $manajer = $this->buatUser('manajer_cabang', $this->cabang->id);
        $payload = ['jenis' => 'harian', 'tanggal_mulai' => '2025-01-10', 'tanggal_selesai' => '2025-01-10', 'cakupan' => (string) $this->cabang->id];

        $this->actingAs($manajer)->post(route('closing.store'), $payload + ['aksi' => 'final'])->assertForbidden();
        $this->actingAs($manajer)->post(route('closing.store'), $payload + ['aksi' => 'draft'])->assertRedirect();
        $draft = ClosingPeriode::where('cabang_id', $this->cabang->id)->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->actingAs($manajer)->post(route('closing.finalize', $draft))->assertForbidden();
        $this->actingAs($manajer)->post(route('closing.unlock', $draft), ['alasan_buka' => 'coba coba'])->assertForbidden();

        $admin = $this->buatUser('admin_pusat');
        $this->actingAs($admin)->post(route('closing.finalize', $draft))->assertRedirect(route('closing.show', $draft));
        $this->assertSame('final', $draft->fresh()->status);
        $this->actingAs($admin)->post(route('closing.unlock', $draft), ['alasan_buka' => 'coba coba'])->assertForbidden();
    }

    public function test_manajer_tidak_bisa_konsolidasi_atau_cabang_lain(): void
    {
        $manajer = $this->buatUser('manajer_cabang', $this->cabang->id);
        $lain = $this->bikinCabang('CLM');
        $base = ['jenis' => 'harian', 'tanggal_mulai' => '2025-01-10', 'tanggal_selesai' => '2025-01-10', 'aksi' => 'draft'];

        $this->actingAs($manajer)->post(route('closing.store'), $base + ['cakupan' => 'konsolidasi'])->assertForbidden();
        $this->actingAs($manajer)->post(route('closing.store'), $base + ['cakupan' => (string) $lain->id])->assertForbidden();

        $closingLain = $this->closingFinal($lain->id, '2025-01-01', '2025-01-02');
        $this->actingAs($manajer)->get(route('closing.show', $closingLain))->assertForbidden();
    }

    public function test_kasir_tidak_punya_akses(): void
    {
        $kasir = $this->buatUser('kasir', $this->cabang->id);
        $this->actingAs($kasir)->get(route('closing.index'))->assertForbidden();
        $this->actingAs($kasir)->get(route('closing.create'))->assertForbidden();
    }

    public function test_halaman_index_create_preview_show_dan_export(): void
    {
        $this->order('2025-01-10', 100000, 40000);
        $owner = $this->buatUser('owner');

        $this->actingAs($owner)->get(route('closing.index'))->assertOk()->assertSee('Closing Periode');
        $this->actingAs($owner)->get(route('closing.create'))->assertOk()->assertSee('Siklus tgl');
        $this->actingAs($owner)->get(route('closing.preview', [
            'jenis' => 'bulanan', 'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-01-31', 'cakupan' => (string) $this->cabang->id,
        ]))->assertOk()->assertSee('Rp 100.000')->assertSee('Kunci Closing (Final)');

        $this->actingAs($owner)->post(route('closing.store'), [
            'jenis' => 'bulanan', 'tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-01-31',
            'cakupan' => (string) $this->cabang->id, 'aksi' => 'final', 'catatan' => 'test',
        ])->assertRedirect();
        $c = ClosingPeriode::where('cabang_id', $this->cabang->id)->firstOrFail();

        $this->actingAs($owner)->get(route('closing.show', $c))->assertOk()->assertSee($c->kode)->assertSee('Buka Kunci');
        $this->actingAs($owner)->get(route('closing.show', [$c, 'bandingkan' => 1]))->assertOk()->assertSee('Data Saat Ini');

        $pdf = $this->actingAs($owner)->get(route('closing.pdf', $c));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $this->actingAs($owner)->get(route('closing.excel', $c))->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_preview_tanggal_masa_depan_kembali_ke_form_dengan_error(): void
    {
        $owner = $this->buatUser('owner');
        $this->actingAs($owner)->get(route('closing.preview', [
            'jenis' => 'harian', 'tanggal_mulai' => today()->toDateString(), 'tanggal_selesai' => today()->addDays(2)->toDateString(),
            'cakupan' => (string) $this->cabang->id,
        ]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_hapus_draft_boleh_hapus_final_ditolak(): void
    {
        $owner = $this->buatUser('owner');
        $draft = $this->svc->simpan('harian', $this->cabang->id, Carbon::parse('2025-01-05'), Carbon::parse('2025-01-05'), $owner, false);
        $this->actingAs($owner)->delete(route('closing.destroy', $draft))->assertRedirect(route('closing.index'));
        $this->assertDatabaseMissing('closing_periodes', ['id' => $draft->id]);

        $final = $this->closingFinal($this->cabang->id, '2025-01-06', '2025-01-06');
        $this->actingAs($owner)->delete(route('closing.destroy', $final))->assertSessionHas('error');
        $this->assertDatabaseHas('closing_periodes', ['id' => $final->id]);
    }
}
