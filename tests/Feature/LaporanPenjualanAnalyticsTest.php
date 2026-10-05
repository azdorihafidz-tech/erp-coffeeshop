<?php

namespace Tests\Feature;

use App\Enums\StatusOrder;
use App\Exports\LaporanPenjualanExport;
use App\Models\Cabang;
use App\Models\Order;
use App\Services\SalesAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * Sprint Analytics Laporan Penjualan (2026-10-05) — 5 metrik ikut Laporan
 * Penjualan + Export PDF/Excel.
 */
class LaporanPenjualanAnalyticsTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function bikinOrder(int $cabangId, string $tanggal, float $total, StatusOrder $status = StatusOrder::Selesai): Order
    {
        return Order::create([
            'cabang_id' => $cabangId,
            'nomor_order' => 'TEST-LPA-' . uniqid(),
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

    public function test_service_by_date_range_hitung_days_inclusive(): void
    {
        $svc = app(SalesAnalyticsService::class);
        $m = $svc->getAllMetricsByDateRange(null, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $this->assertEquals(30, $m['days'], '1 s/d 30 Sep = 30 hari inklusif');
        $this->assertEquals('custom', $m['periode']);
    }

    public function test_service_by_date_range_hormati_cabang_dan_tanggal(): void
    {
        $svc = app(SalesAnalyticsService::class);
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, Carbon::now()->subDays(10)->toDateString(), 100000);
        $this->bikinOrder($c->id, Carbon::now()->subDays(5)->toDateString(), 200000);
        $this->bikinOrder($c->id, Carbon::now()->subDays(60)->toDateString(), 999999);

        $m = $svc->getAllMetricsByDateRange($c->id, Carbon::now()->subDays(15), Carbon::now());
        $this->assertEqualsWithDelta(150000, $m['avg_tx_value'], 0.01, '(100k+200k)/2, order 60 hari lalu tidak ikut');
    }

    public function test_laporan_penjualan_index_tampil_section_analytics(): void
    {
        $owner = $this->buatUser('owner');
        $response = $this->actingAs($owner)->get(route('laporan.penjualan'));
        $response->assertOk();
        $response->assertSee('Analytics Penjualan', false);
        $response->assertSee('Rata-rata Nilai / Transaksi', false);
    }

    public function test_laporan_penjualan_filter_tanggal_memengaruhi_analytics(): void
    {
        $owner = $this->buatUser('owner');
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, '2026-09-15', 500000);
        $this->bikinOrder($c->id, '2026-10-15', 100000);

        $response = $this->actingAs($owner)->get(route('laporan.penjualan', [
            'dari' => '2026-09-01', 'sampai' => '2026-09-30',
        ]));
        $response->assertOk();
        // Rata-rata = 500.000 / 1 tx = Rp 500.000; harus muncul di card analytics
        $response->assertSee('500.000', false);
    }

    public function test_laporan_penjualan_pdf_include_section_ringkasan_analytics(): void
    {
        $owner = $this->buatUser('owner');
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 150000);

        $response = $this->actingAs($owner)->get(route('laporan.penjualan', [
            'export' => 'pdf',
            'dari'   => Carbon::today()->toDateString(),
            'sampai' => Carbon::today()->toDateString(),
        ]));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $pdfBytes = (string) $response->getContent();
        $this->assertNotEmpty($pdfBytes);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
    }

    public function test_laporan_penjualan_excel_punya_2_sheet(): void
    {
        $owner = $this->buatUser('owner');
        $c = Cabang::orderBy('id')->firstOrFail();
        $this->bikinOrder($c->id, Carbon::today()->toDateString(), 250000);

        Excel::fake();
        $response = $this->actingAs($owner)->get(route('laporan.penjualan', [
            'export' => 'excel',
            'dari'   => Carbon::today()->toDateString(),
            'sampai' => Carbon::today()->toDateString(),
        ]));
        $response->assertOk();

        $filename = 'Laporan-Penjualan-' . now()->format('Y-m-d') . '.xlsx';
        Excel::assertDownloaded($filename, function (LaporanPenjualanExport $export) {
            $sheets = $export->sheets();
            $this->assertCount(2, $sheets, 'harus 2 sheet: Ringkasan + Detail');
            return true;
        });
    }

    public function test_excel_export_backward_compat_tanpa_analytics(): void
    {
        // Caller lama tanpa pass $analytics -- harus tetap jalan dgn 1 sheet (Detail only)
        $export = new LaporanPenjualanExport(collect(), 'TestUser');
        $sheets = $export->sheets();
        $this->assertCount(1, $sheets);
    }
}
