<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\PengaturanUmum;
use App\Support\Branding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Tahap25\Concerns\CreatesTahap25Users;
use Tests\TestCase;

/**
 * 4.54 Warna Tampilan di Pengaturan Umum — kosong = warna bawaan (tampilan tidak
 * berubah), tema/warna sendiri ikut ke semua layout + tombol Bootstrap, warna
 * yang membuat tulisan tidak terbaca ditolak, kembalikan ke bawaan.
 */
class WarnaTampilanTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesTahap25Users;

    private function owner()
    {
        $cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();

        return [$this->buatUser('owner', $cabang->id), $cabang];
    }

    private function simpan($user, array $warna)
    {
        $s = PengaturanUmum::firstOrCreate([], ['nama_perusahaan' => 'Usaha Uji']);

        return $this->actingAs($user)->put(route('pengaturan.umum.update'), array_merge([
            'nama_perusahaan' => $s->nama_perusahaan ?: 'Usaha Uji',
            'nama_singkat'    => $s->nama_singkat,
        ], $warna));
    }

    private function bersihkanWarna(): void
    {
        PengaturanUmum::query()->update(['warna_utama' => null, 'warna_sidebar' => null, 'warna_latar' => null]);
        app(Branding::class)->lupakan();
    }

    public function test_bawaan_tampilan_tidak_berubah_dan_tombol_bootstrap_tidak_diubah(): void
    {
        $this->bersihkanWarna();
        [$owner, $cabang] = $this->owner();
        $html = $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])->get(route('penjualan.pos'))->assertOk()->getContent();

        $this->assertFalse(app(Branding::class)->warnaDiatur());
        $this->assertStringContainsString('--sidebar-bg: #1A1A1A;', $html);
        $this->assertStringContainsString('background-color: #F5F0E6;', $html);
        $this->assertStringContainsString('--kd-utama: #2D6A4F;', $html);
        $this->assertStringNotContainsString('--bs-btn-bg', $html, 'warna bawaan: tombol biru Bootstrap tidak disentuh');
    }

    public function test_simpan_tema_berlaku_di_layout_login_error_dan_pwa(): void
    {
        $this->bersihkanWarna();
        [$owner, $cabang] = $this->owner();
        $t = Branding::TEMA['cokelat'];
        $this->simpan($owner, ['warna_utama' => strtolower($t['utama']), 'warna_sidebar' => $t['sidebar'], 'warna_latar' => $t['latar']])
            ->assertRedirect(route('pengaturan.umum'))->assertSessionHasNoErrors();

        $s = PengaturanUmum::first();
        $this->assertSame('#7B4B2A', $s->warna_utama, 'disimpan huruf besar');
        $this->assertSame('#2B1B12', $s->warna_sidebar);

        $html = $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])->get(route('penjualan.pos'))->assertOk()->getContent();
        $this->assertStringContainsString('--sidebar-bg: #2B1B12;', $html);
        $this->assertStringContainsString('background-color: #F6EFE7;', $html);
        $this->assertStringContainsString('--bs-btn-bg: #7B4B2A;', $html, 'tombol primary ikut warna utama');
        $this->assertStringContainsString('--bs-primary-rgb: 123,75,42;', $html);
        $this->assertStringNotContainsString('#2D6A4F', $html, 'tidak ada sisa hijau bawaan di POS');

        // halaman lain yang memakai warna
        $this->actingAs($owner)->get('/halaman-yang-tidak-ada-' . uniqid())->assertNotFound()->assertSee('#7B4B2A', false);
        $this->actingAs($owner)->get(route('pengaturan.umum'))->assertOk()->assertSee('Warna sendiri')->assertSee('Cokelat Kopi');
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('#2B1B12', false)->assertSee('--bs-btn-bg: #7B4B2A;', false);
        $this->get('/manifest.json')->assertOk()->assertJsonFragment(['theme_color' => '#7B4B2A', 'background_color' => '#2B1B12']);
    }

    public function test_warna_tidak_terbaca_ditolak_dan_data_tidak_berubah(): void
    {
        $this->bersihkanWarna();
        [$owner] = $this->owner();

        $this->simpan($owner, ['warna_utama' => '#FFE082'])->assertSessionHasErrors('warna_utama');
        $this->simpan($owner, ['warna_sidebar' => '#DDDDDD'])->assertSessionHasErrors('warna_sidebar');
        $this->simpan($owner, ['warna_latar' => '#333333'])->assertSessionHasErrors('warna_latar');
        $this->simpan($owner, ['warna_utama' => '#F2EBE0'])->assertSessionHasErrors('warna_utama'); // terlalu mirip latar
        $this->simpan($owner, ['warna_utama' => 'hijau'])->assertSessionHasErrors('warna_utama');
        $this->simpan($owner, ['warna_utama' => '#12345'])->assertSessionHasErrors('warna_utama');

        $s = PengaturanUmum::first();
        $this->assertNull($s->warna_utama);
        $this->assertNull($s->warna_sidebar);
        $this->assertNull($s->warna_latar);
    }

    public function test_satu_warna_saja_dan_kembalikan_ke_bawaan(): void
    {
        $this->bersihkanWarna();
        [$owner, $cabang] = $this->owner();

        $this->simpan($owner, ['warna_utama' => '#1D4E89'])->assertSessionHasNoErrors();
        $s = PengaturanUmum::first();
        $this->assertSame('#1D4E89', $s->warna_utama);
        $this->assertNull($s->warna_sidebar, 'warna lain tetap bawaan');
        $html = $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])->get(route('dashboard.pusat'))->assertOk()->getContent();
        $this->assertStringContainsString('--sidebar-bg: #1A1A1A;', $html);
        $this->assertStringContainsString('--sidebar-active: #1D4E89;', $html);

        $this->simpan($owner, ['warna_utama' => '#1D4E89', 'reset_warna' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(PengaturanUmum::first()->warna_utama);
        $html = $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])->get(route('dashboard.pusat'))->assertOk()->getContent();
        $this->assertStringNotContainsString('--bs-btn-bg', $html);
        $this->assertStringContainsString('--sidebar-active: #2D6A4F;', $html);
    }

    public function test_kasir_tidak_bisa_mengubah_warna(): void
    {
        $this->bersihkanWarna();
        $cabang = Cabang::where('tipe', 'cabang')->where('is_active', true)->firstOrFail();
        $this->actingAs($this->buatUser('kasir', $cabang->id))
            ->put(route('pengaturan.umum.update'), ['nama_perusahaan' => 'X', 'warna_utama' => '#1D4E89'])->assertForbidden();
        $this->assertNull(PengaturanUmum::first()?->warna_utama);
    }

    public function test_pdf_dan_kitchen_display_jalan_dengan_warna_bawaan_dan_warna_sendiri(): void
    {
        $this->bersihkanWarna();
        [$owner, $cabang] = $this->owner();
        $meja = \App\Models\Meja::create(['cabang_id' => $cabang->id, 'nomor_meja' => (string) random_int(700, 999), 'nama_meja' => 'Meja Warna',
            'kapasitas' => 2, 'lokasi' => 'indoor', 'qr_token' => \App\Models\Meja::generateQrToken(), 'qr_type' => 'permanent', 'status' => 'aktif']);

        foreach ([null, Branding::TEMA['biru']] as $tema) {
            if ($tema) {
                $this->simpan($owner, ['warna_utama' => $tema['utama'], 'warna_sidebar' => $tema['sidebar'], 'warna_latar' => $tema['latar']])->assertSessionHasNoErrors();
            }
            $utama = $tema['utama'] ?? '#2D6A4F';

            $pdf = $this->actingAs($owner)->get(route('laporan.neraca.export'));
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent(), 'PDF neraca ' . $utama);

            $qr = $this->actingAs($owner)->get(route('meja.print-qr', $meja));
            $qr->assertOk();
            $this->assertStringStartsWith('%PDF-', $qr->getContent(), 'stiker QR ' . $utama);

            $this->actingAs($owner)->withSession(['active_cabang_id' => $cabang->id])
                ->get(route('kitchen.display', $cabang->id))->assertOk()->assertSee($utama, false);
            $this->actingAs($owner)->get(route('laporan.neraca.index'))->assertOk();
        }
    }

    public function test_aturan_warna_dan_tema(): void
    {
        foreach (Branding::TEMA as $kode => $t) {
            $this->assertSame([], Branding::cekKeterbacaan($t['utama'], $t['sidebar'], $t['latar']), "tema $kode harus lolos");
        }
        $b = Branding::WARNA_BAWAAN;
        $this->assertSame([], Branding::cekKeterbacaan($b['utama'], $b['sidebar'], $b['latar']));
        $this->assertSame('#2B2B2B', Branding::campur('#1A1A1A', '#FFFFFF', 0.075), 'gradasi login bawaan tetap sama');
        $this->assertSame('rgba(45,106,79,0.4)', Branding::rgba('#2D6A4F', 0.4));
        $this->assertEqualsWithDelta(21.0, Branding::kontras('#000000', '#FFFFFF'), 0.01);

        // Sebelum migration 4.54 dijalankan, data tidak punya kunci warna → bawaan
        $brand = new Branding();
        \Closure::bind(fn () => $this->data = ['nama' => 'X'], $brand, Branding::class)();
        $this->assertSame('#2D6A4F', $brand->warnaUtama());
        $this->assertFalse($brand->warnaDiatur());
    }
}
