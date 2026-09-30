<?php

namespace Tests\Feature;

use App\Enums\StatusOrder;
use App\Models\Cabang;
use App\Models\Order;
use App\Services\SalesAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Analytics Dashboard (2026-10-02) — 5 metrik penjualan + filter
 * periode global untuk /dashboard/pusat & /dashboard/cabang.
 */
class SalesAnalyticsTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private SalesAnalyticsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(SalesAnalyticsService::class);
    }

    private function bikinOrder(int $cabangId, string $tanggal, float $total, StatusOrder $status = StatusOrder::Selesai): Order
    {
        return Order::create([
            'cabang_id' => $cabangId,
            'nomor_order' => 'TEST-SA-' . uniqid(),
            'tanggal_order' => $tanggal,
            'tipe_order' => 'penjualan',
            'tipe_transaksi' => 'takeaway',
            'total_harga' => $total,
            'total_bayar' => $total,
            'jumlah_bayar' => $total,
            'tipe_pembayaran' => 'tunai',
            'status' => $status,
        ]);
    }

    public function test_keseluruhan_pakai_semua_data(): void
    {
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, Carbon::now()->subMonths(6)->toDateString(), 50000);
        $this->bikinOrder($c->id, Carbon::now()->subMonths(3)->toDateString(), 100000);
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 200000);

        $m = $this->svc->getAllMetrics($c->id, 'keseluruhan');
        // 3 tx dilihat, avg = (50k+100k+200k) / 3 = 116.666,67
        $this->assertEqualsWithDelta(116666.67, $m['avg_tx_value'], 0.01);
    }

    public function test_bulan_hanya_hitung_bulan_berjalan(): void
    {
        $c = Cabang::orderBy('id')->firstOrFail();
        // Tx bulan lalu — tidak masuk
        $this->bikinOrder($c->id, Carbon::now()->subMonth()->toDateString(), 999999);
        // Tx bulan ini
        $this->bikinOrder($c->id, Carbon::now()->startOfMonth()->toDateString(), 100000);
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 200000);

        $m = $this->svc->getAllMetrics($c->id, 'bulan');
        $this->assertEqualsWithDelta(150000, $m['avg_tx_value'], 0.01);
    }

    public function test_hari_ini_kosong_kalau_belum_ada_transaksi(): void
    {
        $c = Cabang::orderBy('id')->firstOrFail();
        // Hanya tx kemarin, tidak ada tx hari ini
        $this->bikinOrder($c->id, Carbon::yesterday()->toDateString(), 100000);

        $m = $this->svc->getAllMetrics($c->id, 'hari');
        $this->assertNull($m['avg_tx_value']);
        $this->assertNull($m['avg_daily_tx']);
        $this->assertNull($m['avg_daily_revenue']);
        $this->assertNull($m['top_value_day']);
        $this->assertNull($m['top_qty_day']);
    }

    public function test_order_dibatalkan_tidak_ikut_dihitung(): void
    {
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 100000);
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 999999, StatusOrder::Dibatalkan);

        $m = $this->svc->getAllMetrics($c->id, 'hari');
        $this->assertEqualsWithDelta(100000, $m['avg_tx_value'], 0.01);
    }

    public function test_hari_tertinggi_pilih_omzet_bukan_qty_untuk_top_value(): void
    {
        $c = Cabang::orderBy('id')->firstOrFail();
        // Tanggal A: 5 tx kecil (5 x 10.000 = 50.000)
        $tglA = Carbon::today()->subDays(2)->toDateString();
        for ($i = 0; $i < 5; $i++) $this->bikinOrder($c->id, $tglA, 10000);
        // Tanggal B: 1 tx besar (1 x 100.000)
        $tglB = Carbon::today()->subDay()->toDateString();
        $this->bikinOrder($c->id, $tglB, 100000);

        $m = $this->svc->getAllMetrics($c->id, 'keseluruhan');
        $this->assertEquals($tglB, $m['top_value_day']['tanggal']->toDateString(), 'top_value_day = tgl B (omzet tertinggi)');
        $this->assertEquals($tglA, $m['top_qty_day']['tanggal']->toDateString(), 'top_qty_day = tgl A (jumlah tertinggi)');
    }

    public function test_cabang_id_filter_isolasi_data_antar_cabang(): void
    {
        $c1 = Cabang::orderBy('id')->firstOrFail();
        $c2 = Cabang::orderBy('id')->skip(1)->firstOrFail();

        $this->bikinOrder($c1->id, Carbon::today()->toDateString(), 100000);
        $this->bikinOrder($c2->id, Carbon::today()->toDateString(), 500000);

        $m1 = $this->svc->getAllMetrics($c1->id, 'hari');
        $m2 = $this->svc->getAllMetrics($c2->id, 'hari');
        $mAll = $this->svc->getAllMetrics(null, 'hari');

        $this->assertEqualsWithDelta(100000, $m1['avg_tx_value'], 0.01);
        $this->assertEqualsWithDelta(500000, $m2['avg_tx_value'], 0.01);
        // Gabungan: (100k + 500k) / 2 = 300k
        $this->assertEqualsWithDelta(300000, $mAll['avg_tx_value'], 0.01);
    }

    public function test_periode_invalid_fallback_ke_default_bulan(): void
    {
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 100000);

        $m = $this->svc->getAllMetrics($c->id, 'periode-yang-tidak-ada');
        $this->assertEquals('bulan', $m['periode']);
        $this->assertEquals('Bulan Ini', $m['periode_label']);
    }

    public function test_dashboard_pusat_route_render_dgn_analytics_cards(): void
    {
        $owner = $this->buatUser('owner');
        $response = $this->actingAs($owner)->get(route('dashboard.pusat'));
        $response->assertOk();
        $response->assertSee('Analytics Penjualan', false);
        $response->assertSee('Rata-rata Nilai per Transaksi', false);
    }

    public function test_dashboard_cabang_route_render_dgn_analytics_cards(): void
    {
        $cabang = Cabang::orderBy('id')->firstOrFail();
        $manajer = $this->buatUser('manajer_cabang', $cabang->id);
        $response = $this->actingAs($manajer)->get(route('dashboard.cabang'));
        $response->assertOk();
        $response->assertSee('Analytics Penjualan', false);
        $response->assertSee('Rata-rata Omzet Harian', false);
    }

    public function test_dropdown_filter_terpilih_dipertahankan_setelah_reload(): void
    {
        $owner = $this->buatUser('owner');
        $response = $this->actingAs($owner)->get(route('dashboard.pusat', ['periode' => 'hari']));
        $response->assertOk();
        // Option "Hari Ini" harus punya atribut selected
        $response->assertSee('value="hari" selected', false);
    }
}
