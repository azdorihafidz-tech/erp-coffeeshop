<?php

namespace Tests\Feature;

use App\Models\Cabang;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.52 Tahap C+D (porting dimsum 4.47) — klik produk yang sama = qty +1 (kecuali baris
 * bercatatan / varian beda), ringkasan "qty satuan x Rp harga" seperti struk.
 * Perilaku JS diuji di Chrome (scratchpad uji_452.js); test ini menjaga kodenya tetap ada.
 */
class PosGabungBarisTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    public function test_kode_gabung_baris_dan_ringkasan_struk_ada(): void
    {
        $cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $html = $this->actingAs($this->buatUser('kasir', $cabang->id))->withSession(['active_cabang_id' => $cabang->id])
            ->get(route('penjualan.pos'))->assertOk()->getContent();

        $this->assertStringContainsString('function gabungKeBarisSama(item, variant)', $html);
        $this->assertStringContainsString('function tambahkanItemKeForm(item, variant, gabung = true)', $html);
        $this->assertStringContainsString('if (gabung && gabungKeBarisSama(item, variant)) return;', $html);
        $this->assertStringContainsString("if (cat && cat.value.trim() !== '') continue;", $html, 'baris bercatatan tidak digabung');
        $this->assertStringContainsString('[item_variant_id]"]`);', $html, 'varian dibandingkan');
        $this->assertStringContainsString('.pos-item-row.pos-item-tambah', $html);
        $this->assertStringContainsString('${escHtml(satuan)} x Rp ${harga.toLocaleString(\'id-ID\')}', $html, 'ringkasan seperti struk');
        // Item Tambahan & grid produk sama-sama lewat tambahkanItemKeForm (ikut digabung)
        $this->assertSame(1, substr_count($html, "function klikItemTambahanGrid(itemId) {\n    const item = produkJadi.find(p => p.id === itemId);\n    if (!item || !item.bisa_dijual) return;\n    tambahkanItemKeForm(item, null);"));
    }
}
