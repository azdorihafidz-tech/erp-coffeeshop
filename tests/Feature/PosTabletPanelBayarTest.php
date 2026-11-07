<?php

namespace Tests\Feature;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.52 (porting dimsum 4.47) — panel Ringkasan & Pembayaran mode Tablet: isi scroll sendiri,
 * Total + PROSES ORDER + Save Bill di kaki panel. Tombol WAJIB tetap di dalam form POS.
 */
class PosTabletPanelBayarTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_struktur_panel_bayar_dan_tombol_tetap_di_form(): void
    {
        $cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $html = $this->actingAs($this->buatUser('owner'))->withSession(['active_cabang_id' => $cabang->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $ada = fn (string $q) => $xp->query($q)->length;

        $this->assertSame(1, $ada('//form[@id="formPos"]//div[@id="posKolomKiri"]'));
        $this->assertSame(1, $ada('//form[@id="formPos"]//div[@id="posKolomKanan"]//div[@id="cardRingkasan"]'));
        $this->assertSame(1, $ada('//div[@id="cardRingkasan"]/div[@id="panelBayarIsi"]//*[@id="totalBayarDisplay"]'), 'total utama tetap di isi panel');
        foreach (['btnProses', 'btnSaveBill', 'kakiTotalNilai'] as $id) {
            $this->assertSame(1, $ada('//form[@id="formPos"]//div[@id="cardRingkasan"]/div[@id="panelBayarKaki"]//*[@id="' . $id . '"]'), $id . ' di kaki panel & di dalam form');
        }
        $this->assertSame(0, $ada('//div[@id="panelBayarIsi"]//*[@id="btnProses"]'), 'tombol tidak lagi di isi yang ter-scroll');
        $this->assertStringContainsString('body.pos-tablet-mode #panelBayarIsi', $html);
        $this->assertStringContainsString('.pos-kaki-total { display: none; }', $html, 'Desktop: total kaki disembunyikan');
    }
}
